<?php
/**
 * Tests for Block Bindings API "core/post-meta" source.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 * @group block-bindings
 */
class Tests_Block_Bindings_Post_Meta_Source extends WP_UnitTestCase {
	protected static $post;
	protected static $wp_meta_keys_saved;

	/**
	 * Modify the post content.
	 *
	 * @param string $content The new content.
	 */
	private function getModifiedPostContent( $content ) {
		self::$post->post_content = $content;
		// Update the global $post variable to ensure all tests get the correct $post context.
		$this->updateGlobalPost();
		return apply_filters( 'the_content', self::$post->post_content );
	}

	/**
	 * Update the global $post variable.
	 */
	private function updateGlobalPost() {
		global $post;
		$post = self::$post;
	}

	/**
	 * Set up for every method.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post               = $factory->post->create_and_get();
		self::$wp_meta_keys_saved = isset( $GLOBALS['wp_meta_keys'] ) ? $GLOBALS['wp_meta_keys'] : array();
	}

	/**
	 * Tear down for every method.
	 */
	public static function wpTearDownAfterClass() {
		$GLOBALS['wp_meta_keys'] = self::$wp_meta_keys_saved;
	}

	/**
	 * Tests that a block connected to a custom field renders its value.
	 *
	 * @ticket 60651
	 */
	public function test_custom_field_value_is_rendered() {
		register_meta(
			'post',
			'tests_custom_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Custom field value',
			)
		);

		$content = $this->getModifiedPostContent( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_custom_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );
		$this->assertSame(
			'<p>Custom field value</p>',
			$content,
			'The post content should show the value of the custom field . '
		);
	}

	/**
	 * Tests that a blocks connected in a password protected post don't render the value.
	 *
	 * @ticket 60651
	 */
	public function test_custom_field_value_is_not_shown_in_password_protected_posts() {
		register_meta(
			'post',
			'tests_custom_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Custom field value',
			)
		);

		function wp_tests_require_post_password() {
			return true;
		}

		add_filter(
			'post_password_required',
			'wp_tests_require_post_password'
		);

		$content = $this->getModifiedPostContent( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_custom_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		remove_filter(
			'post_password_required',
			'wp_tests_post_password_required'
		);

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value instead of the custom field value.'
		);
	}

	/**
	 * Tests that a blocks connected in a post that is not publicly viewable don't render the value.
	 *
	 * @ticket 60651
	 */
	public function test_custom_field_value_is_not_shown_in_non_viewable_posts() {
		register_meta(
			'post',
			'tests_custom_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Custom field value',
			)
		);

		function wp_tests_make_post_status_not_viewable() {
			return false;
		}

		add_filter(
			'is_post_status_viewable',
			'wp_tests_make_post_status_not_viewable'
		);

		$content = $this->getModifiedPostContent( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_custom_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		remove_filter(
			'is_post_status_viewable',
			'wp_tests_make_post_status_not_viewable'
		);

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value instead of the custom field value.'
		);
	}

	/**
	 * Tests that a block connected to a meta key that doesn't exist renders the fallback.
	 *
	 * @ticket 60651
	 */
	public function test_binding_to_non_existing_meta_key() {
		$content = $this->getModifiedPostContent( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_non_existing_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value.'
		);
	}

	/**
	 * Tests that a block connected without specifying the custom field renders the fallback.
	 *
	 * @ticket 60651
	 */
	public function test_binding_without_key_renders_the_fallback() {
		$content = $this->getModifiedPostContent( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta"}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value.'
		);
	}

	/**
	 * Tests that a block connected to a protected field doesn't show the value.
	 *
	 * @ticket 60651
	 */
	public function test_protected_field_value_is_not_shown() {
		register_meta(
			'post',
			'_tests_protected_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Protected value',
			)
		);

		$content = $this->getModifiedPostContent( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"_tests_protected_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value instead of the protected value.'
		);
	}

	/**
	 * Tests that a block connected to a field not exposed in the REST API doesn't show the value.
	 *
	 * @ticket 60651
	 */
	public function test_custom_field_not_exposed_in_rest_api_is_not_shown() {
		register_meta(
			'post',
			'tests_show_in_rest_false_field',
			array(
				'show_in_rest' => false,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Protected value',
			)
		);

		$content = $this->getModifiedPostContent( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_show_in_rest_false_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value instead of the protected value.'
		);
	}

	/**
	 * Tests that meta key with unsafe HTML is sanitized.
	 *
	 * @ticket 60651
	 */
	public function test_custom_field_with_unsafe_html_is_sanitized() {
		register_meta(
			'post',
			'tests_unsafe_html_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => '<script>alert("Unsafe HTML")</script>',
			)
		);

		$content = $this->getModifiedPostContent( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_unsafe_html_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>alert(&#8220;Unsafe HTML&#8221;)</p>',
			$content,
			'The post content should not include the script tag.'
		);
	}
}
