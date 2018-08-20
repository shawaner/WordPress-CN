<?php
/**
 * Plugin API: WP_Hook class
 *
 * @package WordPress
 * @subpackage Plugin
 * @since 4.7.0
 */
# 此文件中定义了一个 WP_Hook 类，它用于辅助 WordPress 实现 Plugin (见 wp-includes/plugin.php)。
#
# WordPress 中 Hook 分两种类型： Actions 和 Filters 。
# 虽然它们按照功能被划分为不同的种类，但实际上它们都是通过 WP_Hook 类来实现的。
#
# Hook 这个单词可以直译为“鱼钩”、“钩子”，你可以简单地将它想象成“鱼钩”，并且是包含多个“钩”的“鱼钩”，像这样的：
#   https://assets.academy.com/mgen/42/10065442.jpg
# 
# 就像上面的“鱼钩”可以挂多个诱饵一样，一个 Hook 对象中也可以添加（注册）多个 Actions 或 Filters。
# 每一个 Action 和 Filter 都包含以下信息： 
# 1. 回调函数 - 你可以想象成“我们使用的是全自动弹簧钓鱼竿，鱼咬钩时自动弹起”，我们也希望我们添加的 Action 或 Filter 在某个时刻
#    自动执行某项操作，这便是“回调函数”的工作。
# 2. 优先级 - 当为一个 Hook 对象添加多个 Actions 或 Filters ，我们可以指定优先级，以便控制执行它们的回调函数的顺序。
# 3. 参数个数 - 指定回调函数的参数个数。
#
# WordPress 内置了很多 Hook（存储在 $wp_filter 和 $wp_actions 两个全局变量中，见 wp-includes/plugin.php），
# 每一个 Hook 都是 WP_Hook 类的一个实例（对象），WordPress 为它们分别都挂了一个不同的标签（tag），这些标签用作
# $wp_filter 和 $wp_actions 的下标。
#
# 关于 Hook 的更多描述请参考：
#    https://developer.wordpress.org/plugins/hooks/


/**
 * Core class used to implement action and filter hook functionality.
 *
 * @since 4.7.0
 *
 * @see Iterator
 * @see ArrayAccess
 */
final class WP_Hook implements Iterator, ArrayAccess {

	/**
	 * Hook callbacks.
	 *
	 * @since 4.7.0
	 * @var array
	 */
	# $callbacks 用于存储回调函数相关信息，它使用二维数组的形式进行存储：
	# - 第一维是优先级级别（数字），按优先级高低依次排序，数字越小，优先级级别越高；
	# - 第二维是标识回调函数的唯一ID（通过 _wp_filter_build_unique_id() 获得，通常是函数名），按添加（注册）顺序排序。
	# 通过这样的存储顺序很容易做到：
	# - 调用（回调函数）时，按优先级顺序进行调用；
	# - 优先级相同的回调函数，按添加（注册）顺序进行调用。
	public $callbacks = array();

	/**
	 * The priority keys of actively running iterations of a hook.
	 *
	 * @since 4.7.0
	 * @var array
	 */
	# $iterations 的元素个数同下面 $nesting_level 的值（即 filter 嵌套层数），其中每一个元素储存相应嵌套层 $callbacks 的第一维索引（即优先级列表）。
	# 关于 filter 嵌套请参见下面关于 $nesting_level 的注释。
	private $iterations = array();

	/**
	 * The current priority of actively running iterations of a hook.
	 *
	 * @since 4.7.0
	 * @var array
	 */
	# $current_priority 的元素个数也同下面 $nesting_level 的值（即 filter 嵌套层数），其中每一个元素存储相应嵌套层的当前优先级级别。
	# 关于 filter 嵌套请参见下面关于 $nesting_level 的注释。
	private $current_priority = array();

	/**
	 * Number of levels this hook can be recursively called.
	 *
	 * @since 4.7.0
	 * @var int
	 */
	# 关于 $nesting_level 以及上面的 $iterations 和 $current_priority 的存在意义，本人能想到的情形是：
	# 我们通过 add_filter() 添加的 filter ，其回调函数中可能也会调用 add_filter(), apply_filters() 或 remove_filter() 等，
	# 这样就会导致 apply_filters() 的递归调用。这三个成员变量的存在，是为了保持每一层递归的数据独立。
	private $nesting_level = 0;

	/**
	 * Flag for if we're current doing an action, rather than a filter.
	 *
	 * @since 4.7.0
	 * @var bool
	 */
	private $doing_action = false;

	/**
	 * Hooks a function or method to a specific filter action.
	 *
	 * @since 4.7.0
	 *
	 * @param string   $tag             The name of the filter to hook the $function_to_add callback to.
	 * @param callable $function_to_add The callback to be run when the filter is applied.
	 * @param int      $priority        The order in which the functions associated with a
	 *                                  particular action are executed. Lower numbers correspond with
	 *                                  earlier execution, and functions with the same priority are executed
	 *                                  in the order in which they were added to the action.
	 * @param int      $accepted_args   The number of arguments the function accepts.
	 */
	# 添加（注册）一个 filter
	public function add_filter( $tag, $function_to_add, $priority, $accepted_args ) {
		# 生成 标识回调函数 的唯一ID
		$idx = _wp_filter_build_unique_id( $tag, $function_to_add, $priority );
		$priority_existed = isset( $this->callbacks[ $priority ] );

		# 按优先级分类存放回调函数相关信息
		$this->callbacks[ $priority ][ $idx ] = array(
			'function' => $function_to_add,
			'accepted_args' => $accepted_args
		);

		// if we're adding a new priority to the list, put them back in sorted order
		# 按优先级级别排序
		if ( ! $priority_existed && count( $this->callbacks ) > 1 ) {
			ksort( $this->callbacks, SORT_NUMERIC );
		}

		# 如果 add_filter() 调用 源自回调函数内部（嵌套）调用，新增加 filter 后，需要调整外层 iterations 中的优先级排序
		if ( $this->nesting_level > 0 ) {
			$this->resort_active_iterations( $priority, $priority_existed );
		}
	}

	/**
	 * Handles reseting callback priority keys mid-iteration.
	 *
	 * @since 4.7.0
	 *
	 * @param bool|int $new_priority     Optional. The priority of the new filter being added. Default false,
	 *                                   for no priority being added.
	 * @param bool     $priority_existed Optional. Flag for whether the priority already existed before the new
	 *                                   filter was added. Default false.
	 */
	# 若是在 apply_filters() 中调用了 add_filter() 或 remove_filter() ，则需要调整当前迭代中的优先级列表
	private function resort_active_iterations( $new_priority = false, $priority_existed = false ) {
		$new_priorities = array_keys( $this->callbacks );

		// If there are no remaining hooks, clear out all running iterations.
		# 如果 resort_active_iterations() 的调用源自 remove_xxx()，$new_priorities 就可能是个空数组
		if ( ! $new_priorities ) {
			foreach ( $this->iterations as $index => $iteration ) {
				# 原以为这里是个bug（看起来应该使用 unset( $this->iterations[ $index ] );） ，但 unset 了之后，apply_filters() 中
				# 的 do-while 条件判断会报错，因此这里是合理的。
				$this->iterations[ $index ] = $new_priorities; # 这里把 $new_priorities 换成 Array() 会更容易理解些
			}
			return;
		}

		# $new_priorities 不应该是已经排过序了吗，第一个元素就是最小值，何需调用 min() ？
		$min = min( $new_priorities );
		foreach ( $this->iterations as $index => &$iteration ) {
			$current = current( $iteration );
			// If we're already at the end of this iteration, just leave the array pointer where it is.
			if ( false === $current ) {
				continue;
			}

			# 因为插入或删除 filter 可能会导致优先级列表（callbacks 的第一维）发生变化，因此这里更新至 iterations
			$iteration = $new_priorities;

			# 个人理解是： $current 小于 $min 的唯一可能性是“原来 $current 位置已被删除”，这里在 $iteration 的头部
			# 添加 $current 是为了保持 apply_filters() 中 do-while 循环迭代（next()进入下一次循环）
			if ( $current < $min ) {
				array_unshift( $iteration, $current );
				continue;
			}

			# 前面重新设置了 $iteration 的值，这回导致它原来的 current 指针被重置，前面记录了旧的 $current 值，
			# 这里将 current 指针移回原来的位置（有可能原来的元素已被删除，那么移回原来下一个值）。
			# 这意味着，若添加了较高优先级（数字越小）的新 filter ，将不会被执行；而添加的较低优先级（数字越大）的新filter，将会被执行，例如：
			#   add_filter('tagx', 'foo', 10, 1);
			#   function foo() {
			#       add_filter('tagy', 'bar', 9, 1);
			#       add_filter('tagz', 'qux', 11, 1);
			# 	}
			#   apply_filters('anything', array(123));		# 首先调用 foo()，再调用 qux()，bar()不会被调用
			# 这一点感觉很奇怪哦！不过若不还原 $iteration 的话，就会进入递归死循环，更糟糕！
			while ( current( $iteration ) < $current ) {
				if ( false === next( $iteration ) ) {
					break;
				}
			}

			// If we have a new priority that didn't exist, but ::apply_filters() or ::do_action() thinks it's the current priority...
			# 搜索源码，resort_active_iterations() 调用总共 3 处，只有 add_filter() 中的调用显式指定了参数，
			# 想不明白：如果是新添加的 filter ，$this->current_priority 中怎么可能有与新优先级相同的元素，bug？？？
			if ( $new_priority === $this->current_priority[ $index ] && ! $priority_existed ) {
				/*
				 * ... and the new priority is the same as what $this->iterations thinks is the previous
				 * priority, we need to move back to it.
				 */

				if ( false === current( $iteration ) ) {
					// If we've already moved off the end of the array, go back to the last element.
					$prev = end( $iteration );
				} else {
					// Otherwise, just go back to the previous element.
					$prev = prev( $iteration );
				}
				if ( false === $prev ) {
					// Start of the array. Reset, and go about our day.
					reset( $iteration );
				} elseif ( $new_priority !== $prev ) {
					// Previous wasn't the same. Move forward again.
					next( $iteration );
				}
			}
		}
		unset( $iteration );
	}

	/**
	 * Unhooks a function or method from a specific filter action.
	 *
	 * @since 4.7.0
	 *
	 * @param string   $tag                The filter hook to which the function to be removed is hooked. Used
	 *                                     for building the callback ID when SPL is not available.
	 * @param callable $function_to_remove The callback to be removed from running when the filter is applied.
	 * @param int      $priority           The exact priority used when adding the original filter callback.
	 * @return bool Whether the callback existed before it was removed.
	 */
	public function remove_filter( $tag, $function_to_remove, $priority ) {
		# 获取 标识回调函数 的唯一ID
		$function_key = _wp_filter_build_unique_id( $tag, $function_to_remove, $priority );

		# 当且仅当该 回调函数 之前通过某个 action 或 filter 注册过时，才会被删除，否则便是“查无此人”
		$exists = isset( $this->callbacks[ $priority ][ $function_key ] );
		if ( $exists ) {
			unset( $this->callbacks[ $priority ][ $function_key ] );
			if ( ! $this->callbacks[ $priority ] ) {
				unset( $this->callbacks[ $priority ] );
				if ( $this->nesting_level > 0 ) {
					$this->resort_active_iterations();
				}
			}
		}
		return $exists;
	}

	/**
	 * Checks if a specific action has been registered for this hook.
	 *
	 * @since 4.7.0
	 *
	 * @param string        $tag               Optional. The name of the filter hook. Used for building
	 *                                         the callback ID when SPL is not available. Default empty.
	 * @param callable|bool $function_to_check Optional. The callback to check for. Default false.
	 * @return bool|int The priority of that hook is returned, or false if the function is not attached.
	 */
	# 判断是否存在某个 filter ，参数说明：
	# - $tag 用以标识 Hook ，只判定某个指定的 Hook 中是否添加了该 filter
	# - $function_to_check 用以标识该 filter 的回调函数，若该参数为 false ，则判断该 Hook 上是否存在任何 filter
	public function has_filter( $tag = '', $function_to_check = false ) {
		if ( false === $function_to_check ) {
			return $this->has_filters(); # 判断该 Hook 上是否存在任何 filter
		}

		$function_key = _wp_filter_build_unique_id( $tag, $function_to_check, false );
		if ( ! $function_key ) {
			return false;
		}

		# 判断是否存在指定的 filter （通过回调函数信息来判断）
		foreach ( $this->callbacks as $priority => $callbacks ) {
			if ( isset( $callbacks[ $function_key ] ) ) {
				return $priority;
			}
		}

		return false;
	}

	/**
	 * Checks if any callbacks have been registered for this hook.
	 *
	 * @since 4.7.0
	 *
	 * @return bool True if callbacks have been registered for the current hook, otherwise false.
	 */
	# 判断当前 Hook 上是否添加（注册）了任何 filter
	public function has_filters() {
		foreach ( $this->callbacks as $callbacks ) {
			if ( $callbacks ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Removes all callbacks from the current filter.
	 *
	 * @since 4.7.0
	 *
	 * @param int|bool $priority Optional. The priority number to remove. Default false.
	 */
	# 若 $priority 为 false ，则删除所有的 filters
	# 否则，删除指定优先级的 filters
	public function remove_all_filters( $priority = false ) {
		if ( ! $this->callbacks ) {
			return;
		}

		if ( false === $priority ) {
			$this->callbacks = array();
		} else if ( isset( $this->callbacks[ $priority ] ) ) {
			unset( $this->callbacks[ $priority ] );
		}

		if ( $this->nesting_level > 0 ) {
			$this->resort_active_iterations();
		}
	}

	/**
	 * Calls the callback functions added to a filter hook.
	 *
	 * @since 4.7.0
	 *
	 * @param mixed $value The value to filter.
	 * @param array $args  Arguments to pass to callbacks.
	 * @return mixed The filtered value after all hooked functions are applied to it.
	 */
	# 此文件头部注释中以“钓鱼”的示例来描述 Hook 的基本原理，调用下面的 apply_filters() 就好比“鱼咬钩”的过程，
	# 它会触发添加（注册）到该 Hook 对象上的 filters 的回调函数自动被调用（鱼竿自动弹起）。
	#
	# 需要注意的是：为了代码复用，do_action() 也通过调用 apply_filters() 来实现它自己的功能。
	# 因此，下面的代码中，有部分是专门针对 Actions 的（容易造成阅读混淆，在此提醒一下）。
	public function apply_filters( $value, $args ) {
		if ( ! $this->callbacks ) {
			return $value;
		}

		$nesting_level = $this->nesting_level++;

		# 获取优先级级别（迭代）列表（其中每一个元素中包含若干个回调函数列表）
		$this->iterations[ $nesting_level ] = array_keys( $this->callbacks );
		$num_args = count( $args );

		# 遍历 $this->callbacks 的第一维（即优先级列表）
		do {
			# 获取本次循环中的优先级（下面称为“当前优先级”）
			$this->current_priority[ $nesting_level ] = $priority = current( $this->iterations[ $nesting_level ] );

			# 遍历 $this->callbacks 的第二维（即添加到当前优先级的回调函数列表）
			foreach ( $this->callbacks[ $priority ] as $the_ ) {
			    # 若函数调用是从 do_action() 调用过来的，$this->doing_action 才为 true，下面的条件才会为 false
				if( ! $this->doing_action ) {
					$args[ 0 ] = $value;
				}

				# 提示： $the_ (即回调函数信息)中包含两个键值 'function' 和 'accepted_args' （见 add_filter() 函数）

				// Avoid the array_slice if possible.
				# 通过 $the_['function'] 获取回调函数，通过 call_user_func_array() 调用回调函数。
				# 关于 call_user_func_array() 详见： http://php.net/manual/zh/function.call-user-func-array.php
				if ( $the_['accepted_args'] == 0 ) {
					$value = call_user_func_array( $the_['function'], array() );
				} elseif ( $the_['accepted_args'] >= $num_args ) {
					$value = call_user_func_array( $the_['function'], $args );
				} else {
					$value = call_user_func_array( $the_['function'], array_slice( $args, 0, (int)$the_['accepted_args'] ) );
				}
			}
		} while ( false !== next( $this->iterations[ $nesting_level ] ) );

		unset( $this->iterations[ $nesting_level ] );
		unset( $this->current_priority[ $nesting_level ] );

		$this->nesting_level--;

		return $value;
	}

	/**
	 * Executes the callback functions hooked on a specific action hook.
	 *
	 * @since 4.7.0
	 *
	 * @param mixed $args Arguments to pass to the hook callbacks.
	 */
	public function do_action( $args ) {
		$this->doing_action = true;
		$this->apply_filters( '', $args );

		// If there are recursive calls to the current action, we haven't finished it until we get to the last one.
		if ( ! $this->nesting_level ) {
			$this->doing_action = false;
		}
	}

	/**
	 * Processes the functions hooked into the 'all' hook.
	 *
	 * @since 4.7.0
	 *
	 * @param array $args Arguments to pass to the hook callbacks. Passed by reference.
	 */
	public function do_all_hook( &$args ) {
		$nesting_level = $this->nesting_level++;
		$this->iterations[ $nesting_level ] = array_keys( $this->callbacks );

		do {
			$priority = current( $this->iterations[ $nesting_level ] );
			foreach ( $this->callbacks[ $priority ] as $the_ ) {
				call_user_func_array( $the_['function'], $args );
			}
		} while ( false !== next( $this->iterations[ $nesting_level ] ) );

		unset( $this->iterations[ $nesting_level ] );
		$this->nesting_level--;
	}

	/**
	 * Return the current priority level of the currently running iteration of the hook.
	 *
	 * @since 4.7.0
	 *
	 * @return int|false If the hook is running, return the current priority level. If it isn't running, return false.
	 */
	public function current_priority() {
		if ( false === current( $this->iterations ) ) {
			return false;
		}

		return current( current( $this->iterations ) );
	}

	/**
	 * Normalizes filters set up before WordPress has initialized to WP_Hook objects.
	 *
	 * @since 4.7.0
	 * @static
	 *
	 * @param array $filters Filters to normalize.
	 * @return WP_Hook[] Array of normalized filters.
	 */
	public static function build_preinitialized_hooks( $filters ) {
		/** @var WP_Hook[] $normalized */
		$normalized = array();

		foreach ( $filters as $tag => $callback_groups ) {
			if ( is_object( $callback_groups ) && $callback_groups instanceof WP_Hook ) {
				$normalized[ $tag ] = $callback_groups;
				continue;
			}
			$hook = new WP_Hook();

			// Loop through callback groups.
			foreach ( $callback_groups as $priority => $callbacks ) {

				// Loop through callbacks.
				foreach ( $callbacks as $cb ) {
					$hook->add_filter( $tag, $cb['function'], $priority, $cb['accepted_args'] );
				}
			}
			$normalized[ $tag ] = $hook;
		}
		return $normalized;
	}

	/**
	 * Determines whether an offset value exists.
	 *
	 * @since 4.7.0
	 *
	 * @link https://secure.php.net/manual/en/arrayaccess.offsetexists.php
	 *
	 * @param mixed $offset An offset to check for.
	 * @return bool True if the offset exists, false otherwise.
	 */
	public function offsetExists( $offset ) {
		return isset( $this->callbacks[ $offset ] );
	}

	/**
	 * Retrieves a value at a specified offset.
	 *
	 * @since 4.7.0
	 *
	 * @link https://secure.php.net/manual/en/arrayaccess.offsetget.php
	 *
	 * @param mixed $offset The offset to retrieve.
	 * @return mixed If set, the value at the specified offset, null otherwise.
	 */
	public function offsetGet( $offset ) {
		return isset( $this->callbacks[ $offset ] ) ? $this->callbacks[ $offset ] : null;
	}

	/**
	 * Sets a value at a specified offset.
	 *
	 * @since 4.7.0
	 *
	 * @link https://secure.php.net/manual/en/arrayaccess.offsetset.php
	 *
	 * @param mixed $offset The offset to assign the value to.
	 * @param mixed $value The value to set.
	 */
	public function offsetSet( $offset, $value ) {
		if ( is_null( $offset ) ) {
			$this->callbacks[] = $value;
		} else {
			$this->callbacks[ $offset ] = $value;
		}
	}

	/**
	 * Unsets a specified offset.
	 *
	 * @since 4.7.0
	 *
	 * @link https://secure.php.net/manual/en/arrayaccess.offsetunset.php
	 *
	 * @param mixed $offset The offset to unset.
	 */
	public function offsetUnset( $offset ) {
		unset( $this->callbacks[ $offset ] );
	}

	/**
	 * Returns the current element.
	 *
	 * @since 4.7.0
	 *
	 * @link https://secure.php.net/manual/en/iterator.current.php
	 *
	 * @return array Of callbacks at current priority.
	 */
	public function current() {
		return current( $this->callbacks );
	}

	/**
	 * Moves forward to the next element.
	 *
	 * @since 4.7.0
	 *
	 * @link https://secure.php.net/manual/en/iterator.next.php
	 *
	 * @return array Of callbacks at next priority.
	 */
	public function next() {
		return next( $this->callbacks );
	}

	/**
	 * Returns the key of the current element.
	 *
	 * @since 4.7.0
	 *
	 * @link https://secure.php.net/manual/en/iterator.key.php
	 *
	 * @return mixed Returns current priority on success, or NULL on failure
	 */
	public function key() {
		return key( $this->callbacks );
	}

	/**
	 * Checks if current position is valid.
	 *
	 * @since 4.7.0
	 *
	 * @link https://secure.php.net/manual/en/iterator.valid.php
	 *
	 * @return boolean
	 */
	public function valid() {
		return key( $this->callbacks ) !== null;
	}

	/**
	 * Rewinds the Iterator to the first element.
	 *
	 * @since 4.7.0
	 *
	 * @link https://secure.php.net/manual/en/iterator.rewind.php
	 */
	public function rewind() {
		reset( $this->callbacks );
	}

}
