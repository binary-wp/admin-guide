<?php
/**
 * Hooks — the single place every public action/filter is fired from.
 *
 * Each hook fires twice: a generic name every instance shares, then a
 * prefix-scoped name for one booted instance only.
 *
 *     admin_guide_builder/{name}            every instance
 *     {prefix}/admin_guide_builder/{name}   instance booted with {prefix}
 *
 * Hooks that existed before 0.13.0 under `guide_builder/` keep firing under
 * their old names too, through do_action_deprecated() / apply_filters_deprecated(),
 * so existing hosts keep working and get a deprecation notice in debug mode.
 *
 * The full list lives in HOOKS.md.
 */

namespace BinaryWP\AdminGuide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {

	/** Namespace segment of every hook name. */
	const NS = 'admin_guide_builder';

	/** Package version that renamed the legacy `guide_builder/` hooks. */
	const RENAMED_IN = '0.13.0';

	/** Hooks that also fire under their pre-0.13 `guide_builder/` name. */
	const LEGACY = array( 'placeholders', 'integrations', 'system_tabs', 'guide_dir' );

	/**
	 * Fire an action.
	 *
	 * @param Context $context Instance context (supplies the prefix).
	 * @param string  $name    Hook name without namespace, e.g. 'generated'.
	 * @param mixed   ...$args Arguments passed to callbacks.
	 */
	public static function action( Context $context, $name, ...$args ) {
		do_action( self::NS . '/' . $name, ...$args );
		do_action( $context->prefix . '/' . self::NS . '/' . $name, ...$args );

		if ( in_array( $name, self::LEGACY, true ) ) {
			do_action_deprecated( 'guide_builder/' . $name, $args, self::RENAMED_IN, self::NS . '/' . $name );
			do_action_deprecated( $context->prefix . '/guide_builder/' . $name, $args, self::RENAMED_IN, $context->prefix . '/' . self::NS . '/' . $name );
		}
	}

	/**
	 * Run a value through a filter.
	 *
	 * @param Context $context Instance context (supplies the prefix).
	 * @param string  $name    Hook name without namespace, e.g. 'tab_content'.
	 * @param mixed   $value   Value to filter.
	 * @param mixed   ...$args Extra arguments passed to callbacks.
	 * @return mixed
	 */
	public static function filter( Context $context, $name, $value, ...$args ) {
		$value = apply_filters( self::NS . '/' . $name, $value, ...$args );
		$value = apply_filters( $context->prefix . '/' . self::NS . '/' . $name, $value, ...$args );

		if ( in_array( $name, self::LEGACY, true ) ) {
			$value = apply_filters_deprecated( 'guide_builder/' . $name, array_merge( array( $value ), $args ), self::RENAMED_IN, self::NS . '/' . $name );
			$value = apply_filters_deprecated( $context->prefix . '/guide_builder/' . $name, array_merge( array( $value ), $args ), self::RENAMED_IN, $context->prefix . '/' . self::NS . '/' . $name );
		}

		return $value;
	}
}
