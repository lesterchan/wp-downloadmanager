<?php
/**
 * Tests for the blocks.
 *
 * @package WP-DownloadManager
 */

/**
 * The blocks, and the promise that they are an addition rather than a
 * replacement.
 *
 * Most of what is worth asserting here is not "the block renders" -- that is
 * one line -- but the three things a later change could quietly break:
 *
 * * the shortcodes still work, all three of them, because they sit in published
 *   posts everywhere and one of the three is a plural spelling somebody typed
 *   fifteen years ago;
 * * the block and the shortcode render the *same* markup, because they are
 *   meant to share one renderer and nothing else checks that they still do;
 * * neither entry point is implemented in terms of the other, which is what
 *   stops the shortcode's parsing quirks leaking into the block.
 */
class WP_DownloadManager_Blocks_Test extends WP_DownloadManager_TestCase {

	/**
	 * The shortcode table as it stood before a test edited it.
	 *
	 * @var array
	 */
	private $shortcodes;

	/**
	 * Snapshots the global state these tests deliberately break.
	 *
	 * Two tests below unregister a shortcode or a block on purpose, to prove
	 * neither entry point is implemented in terms of the other. Both registries
	 * are process-global and WP_UnitTestCase restores neither, so without this
	 * the first such test silently disarms every test that runs after it -- and
	 * they fail with `[download id="1"]` rendering as literal text, which reads
	 * as a broken shortcode rather than a leaky fixture.
	 */
	public function set_up() {
		parent::set_up();

		$this->shortcodes = $GLOBALS['shortcode_tags'];

		$this->restore_blocks();
	}

	/**
	 * Puts both registries back.
	 */
	public function tear_down() {
		$GLOBALS['shortcode_tags'] = $this->shortcodes;

		$this->restore_blocks();

		parent::tear_down();
	}

	/**
	 * Returns the block registry to exactly the two registered blocks.
	 *
	 * Unregisters before registering rather than registering conditionally:
	 * the plugin has already registered both on `init` by the time any test
	 * runs, and registering a second time is a doing_it_wrong notice that the
	 * suite fails on.
	 *
	 * @return void
	 */
	private function restore_blocks() {
		foreach ( array( 'wp-downloadmanager/download', 'wp-downloadmanager/page-download' ) as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}

		WP_DownloadManager_Blocks::register();
	}

	/**
	 * Renders something with a fresh icon sprite.
	 *
	 * The sprite rides along with the first icon of a request and is silent
	 * afterwards, so two renders in one process are never byte-identical unless
	 * each starts from a clean sheet. That is a fact about the sprite rather
	 * than a difference between the two entry points, and comparing without
	 * this would report it as one.
	 *
	 * @param callable $render What to call.
	 * @return string
	 */
	private function fresh( $render ) {
		WP_DownloadManager_Display::reset_sprite();

		return $render();
	}

	// --- registration ----------------------------------------------------

	/**
	 * Both blocks register, under the prefixed names.
	 *
	 * The `wp-` prefix is deliberate and is the one place the naming rule for
	 * commands and namespaces does not carry: those drop it, because a
	 * collision there is survivable and visible. A block name is written into
	 * post_content and stays there for the life of the post, so a collision
	 * would render another plugin's block inside somebody's published posts.
	 *
	 * @return void
	 */
	public function test_both_blocks_register_under_the_prefixed_name() {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertTrue( $registry->is_registered( 'wp-downloadmanager/download' ), 'The download block registers.' );
		$this->assertTrue( $registry->is_registered( 'wp-downloadmanager/page-download' ), 'The listing block registers.' );

		$this->assertFalse( $registry->is_registered( 'downloadmanager/download' ), 'The unprefixed name is not also claimed.' );
	}

	/**
	 * The plural alias gets no block of its own, and that is the decision.
	 *
	 * `[page_download]` and `[page_downloads]` are one callback which is handed
	 * the attributes and never the tag, so a second block would be the same
	 * block under a second name -- and a block name is written into
	 * post_content, so it would be a second name to support forever.
	 *
	 * @return void
	 */
	public function test_the_plural_spelling_has_no_block() {
		$this->assertFalse(
			WP_Block_Type_Registry::get_instance()->is_registered( 'wp-downloadmanager/page-downloads' ),
			'The plural alias is a shortcode and not a block.'
		);
	}

	/**
	 * The blocks are dynamic, so each carries a render callback.
	 *
	 * Without one a block saves its markup into post_content, and the whole
	 * reason a shortcode and a block can share a renderer is that neither does.
	 *
	 * @return void
	 */
	public function test_the_blocks_are_dynamic() {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertIsCallable( $registry->get_registered( 'wp-downloadmanager/download' )->render_callback, 'The download block renders server-side.' );
		$this->assertIsCallable( $registry->get_registered( 'wp-downloadmanager/page-download' )->render_callback, 'So does the listing block.' );
	}

	/**
	 * The download block's attributes come from block.json rather than from PHP.
	 *
	 * They are the `[download]` shortcode's six, so a post can say in a block
	 * anything it could have said in a shortcode.
	 *
	 * @return void
	 */
	public function test_the_download_block_declares_the_shortcode_attributes() {
		$attributes = WP_Block_Type_Registry::get_instance()->get_registered( 'wp-downloadmanager/download' )->attributes;

		foreach ( array( 'id', 'category', 'display', 'sortBy', 'sortOrder', 'streamLimit' ) as $name ) {
			$this->assertArrayHasKey( $name, $attributes, 'The download block takes ' . $name . '.' );
		}

		$this->assertSame( 'string', $attributes['id']['type'], 'The id is a string, because [download id="1,2,3"] is a list.' );
		$this->assertSame( 'number', $attributes['streamLimit']['type'], 'The stream limit is a count.' );
	}

	/**
	 * And the listing block declares the one attribute its shortcode takes.
	 *
	 * @return void
	 */
	public function test_the_listing_block_declares_its_category() {
		$attributes = WP_Block_Type_Registry::get_instance()->get_registered( 'wp-downloadmanager/page-download' )->attributes;

		$this->assertArrayHasKey( 'category', $attributes, 'The listing block takes a category.' );
		$this->assertSame( 'number', $attributes['category']['type'], 'The category arrives typed, unlike a shortcode attribute.' );
	}

	// --- the shortcodes survive ------------------------------------------

	/**
	 * Adding the blocks unregistered none of the three shortcodes.
	 *
	 * If this ever fails, the blocks have stopped being an addition and become
	 * a replacement, and every published post holding one of these renders
	 * literal text. The plural is in the list on purpose: it has no block, so
	 * it is the one nothing else would notice the loss of.
	 *
	 * @return void
	 */
	public function test_all_three_shortcodes_are_still_registered() {
		$this->assertTrue( shortcode_exists( 'download' ), 'The download shortcode survives the block.' );
		$this->assertTrue( shortcode_exists( 'page_download' ), 'And so does the listing shortcode.' );
		$this->assertTrue( shortcode_exists( 'page_downloads' ), 'And so does its plural spelling, which has no block.' );
	}

	/**
	 * The legacy positional form still renders.
	 *
	 * `[download=1]` is shortcode syntax with no block equivalent, so it stays
	 * in the shortcode callback rather than moving into the shared renderer.
	 *
	 * @return void
	 */
	public function test_the_legacy_positional_shortcode_still_renders() {
		$this->assertStringContainsString( 'The Manual', do_shortcode( '[download=' . $this->ids['public'] . ']' ), 'The legacy form still renders its file.' );
	}

	// --- the block and the shortcode agree -------------------------------

	/**
	 * The download block and the shortcode render one file identically.
	 *
	 * This is the assertion the whole design rests on. Two entry points that
	 * merely both work can drift; two that produce byte-identical markup are
	 * demonstrably going through one renderer.
	 *
	 * @return void
	 */
	public function test_the_download_block_and_the_shortcode_render_the_same_markup() {
		$id = $this->ids['public'];

		$block     = $this->fresh(
			function () use ( $id ) {
				return WP_DownloadManager_Blocks::render_download( array( 'id' => (string) $id ) );
			}
		);
		$shortcode = $this->fresh(
			function () use ( $id ) {
				return do_shortcode( '[download id="' . $id . '"]' );
			}
		);

		$this->assertStringContainsString( 'The Manual', $block, 'The block rendered the file.' );
		$this->assertSame( $shortcode, $block, 'And it is what the shortcode renders.' );
	}

	/**
	 * The same holds for a comma-separated list of ids.
	 *
	 * A list is the reason the block's id attribute is a string rather than a
	 * number: a block whose id could only be one number would be able to say
	 * less than the shortcode it wraps.
	 *
	 * @return void
	 */
	public function test_the_two_entry_points_agree_on_a_list_of_ids() {
		$ids = $this->ids['public'] . ',' . $this->ids['members'];

		$block     = $this->fresh(
			function () use ( $ids ) {
				return WP_DownloadManager_Blocks::render_download( array( 'id' => $ids ) );
			}
		);
		$shortcode = $this->fresh(
			function () use ( $ids ) {
				return do_shortcode( '[download id="' . $ids . '"]' );
			}
		);

		$this->assertStringContainsString( 'The Manual', $block, 'The block rendered the first file.' );
		$this->assertStringContainsString( 'Members Bundle', $block, 'And the second.' );
		$this->assertSame( $shortcode, $block, 'And the two entry points agree.' );
	}

	/**
	 * And for the remaining four attributes, which the block spells in camel
	 * case and the shortcode in snake case.
	 *
	 * The mapping between the two spellings lives in exactly one method, and
	 * this is what would notice it being got wrong.
	 *
	 * @return void
	 */
	public function test_the_two_entry_points_agree_on_the_remaining_attributes() {
		$category = 2;

		$block     = $this->fresh(
			function () use ( $category ) {
				return WP_DownloadManager_Blocks::render_download(
					array(
						'category'  => (string) $category,
						'display'   => 'name',
						'sortBy'    => 'file_name',
						'sortOrder' => 'desc',
					)
				);
			}
		);
		$shortcode = $this->fresh(
			function () use ( $category ) {
				return do_shortcode( '[download category="' . $category . '" display="name" sort_by="file_name" sort_order="desc"]' );
			}
		);

		$this->assertStringContainsString( 'Remote Bundle', $block, 'The block rendered the category.' );
		$this->assertSame( $shortcode, $block, 'And it is what the shortcode renders.' );
	}

	/**
	 * An empty block renders nothing, exactly as an empty shortcode does.
	 *
	 * The two defaults differ in type -- the block's id is an empty string and
	 * the shortcode's is the integer zero -- so they have to be normalised to
	 * the same thing somewhere, or an attributeless block and an attributeless
	 * shortcode ask different questions.
	 *
	 * @return void
	 */
	public function test_neither_entry_point_renders_anything_without_an_id_or_category() {
		$this->assertSame( '', WP_DownloadManager_Blocks::render_download( array() ), 'An attributeless block renders nothing.' );
		$this->assertSame( '', do_shortcode( '[download]' ), 'Nor does an attributeless shortcode.' );
	}

	/**
	 * The listing block and all three spellings of its shortcode agree.
	 *
	 * @return void
	 */
	public function test_the_listing_block_and_both_shortcode_spellings_agree() {
		$block    = $this->fresh(
			function () {
				return WP_DownloadManager_Blocks::render_page_download( array() );
			}
		);
		$singular = $this->fresh(
			function () {
				return do_shortcode( '[page_download]' );
			}
		);
		$plural   = $this->fresh(
			function () {
				return do_shortcode( '[page_downloads]' );
			}
		);

		$this->assertStringContainsString( 'The Manual', $block, 'The listing block renders the listing.' );
		$this->assertSame( $singular, $block, 'And it is what [page_download] renders.' );
		$this->assertSame( $plural, $block, 'And what its plural alias renders, the two being one callback.' );
	}

	/**
	 * The listing block's category means what the shortcode's category means.
	 *
	 * The listing renderer casts its argument on entry, which is what lets the
	 * integer a block declares and the string a shortcode attribute carries
	 * converge on one category.
	 *
	 * @return void
	 */
	public function test_the_listing_block_and_shortcode_agree_on_a_category() {
		$block     = $this->fresh(
			function () {
				return WP_DownloadManager_Blocks::render_page_download( array( 'category' => 2 ) );
			}
		);
		$shortcode = $this->fresh(
			function () {
				return do_shortcode( '[page_download category="2"]' );
			}
		);

		$this->assertStringContainsString( 'Remote Bundle', $block, 'The block rendered the category.' );
		$this->assertStringNotContainsString( 'The Manual', $block, 'And only that category.' );
		$this->assertSame( $shortcode, $block, 'The integer and the string mean one category.' );
	}

	// --- neither is implemented in terms of the other ---------------------

	/**
	 * The blocks do not render by running the shortcodes.
	 *
	 * Routing a block through do_shortcode() would make it inherit shortcode
	 * parsing it has no way to produce, and would break it outright the day
	 * anybody unregistered the shortcode. So: unregister all three, and assert
	 * the blocks carry on rendering.
	 *
	 * @return void
	 */
	public function test_the_blocks_render_with_the_shortcodes_unregistered() {
		remove_shortcode( 'download' );
		remove_shortcode( 'page_download' );
		remove_shortcode( 'page_downloads' );

		$this->assertStringContainsString(
			'The Manual',
			WP_DownloadManager_Blocks::render_download( array( 'id' => (string) $this->ids['public'] ) ),
			'The download block does not need the shortcode.'
		);
		$this->assertStringContainsString(
			'The Manual',
			WP_DownloadManager_Blocks::render_page_download( array() ),
			'Nor does the listing block.'
		);
	}

	/**
	 * The shortcodes do not render by running the blocks.
	 *
	 * The other direction of the same rule, and the one a later "tidy-up" is
	 * likelier to break, because making the shortcode a thin wrapper over the
	 * block reads as removing duplication.
	 *
	 * @return void
	 */
	public function test_the_shortcodes_render_with_the_blocks_unregistered() {
		unregister_block_type( 'wp-downloadmanager/download' );
		unregister_block_type( 'wp-downloadmanager/page-download' );

		$this->assertStringContainsString( 'The Manual', do_shortcode( '[download id="' . $this->ids['public'] . '"]' ), 'The download shortcode does not need the block.' );
		$this->assertStringContainsString( 'The Manual', do_shortcode( '[page_download]' ), 'Nor does the listing shortcode.' );
		$this->assertStringContainsString( 'The Manual', do_shortcode( '[page_downloads]' ), 'Nor its plural alias, which never had one.' );
	}

	// --- the shared renderer ---------------------------------------------

	/**
	 * In a feed, both entry points return the note instead of the file.
	 *
	 * The guard lives in the shared renderer rather than in the shortcode
	 * precisely so the block gets it too: a dynamic block renders in a feed,
	 * and a download link in a feed reader downloads through somebody else's
	 * user agent without ever counting a hit.
	 *
	 * @return void
	 */
	public function test_a_feed_gets_the_note_from_both_entry_points() {
		$this->go_to( '/?feed=rss2' );

		$this->assertTrue( is_feed(), 'The fixture really is a feed request.' );
		$this->assertStringContainsString( 'please visit this post', WP_DownloadManager_Blocks::render_download( array( 'id' => (string) $this->ids['public'] ) ), 'The block returns the note.' );
		$this->assertStringContainsString( 'please visit this post', do_shortcode( '[download id="' . $this->ids['public'] . '"]' ), 'And so does the shortcode.' );
	}

	/**
	 * A hidden file stays hidden through the block.
	 *
	 * `file_permission != -2` is enforced in the query the shared renderer
	 * reaches, so a second entry point cannot become a second way to withdraw
	 * a file and have it listed anyway.
	 *
	 * @return void
	 */
	public function test_the_block_does_not_render_a_hidden_file() {
		$this->assertStringNotContainsString(
			'Hidden File',
			WP_DownloadManager_Blocks::render_download( array( 'id' => (string) $this->ids['hidden'] ) ),
			'A withdrawn file is withdrawn from the block too.'
		);
	}

	// --- rendering through the block parser -------------------------------

	/**
	 * A post holding the block comment renders the file.
	 *
	 * The tests above call the callbacks directly, which does not prove the
	 * registration wired them to the name that gets saved into post_content.
	 * This goes through do_blocks(), the way a published post does.
	 *
	 * @return void
	 */
	public function test_a_saved_download_block_renders_through_the_block_parser() {
		$rendered = do_blocks( '<!-- wp:wp-downloadmanager/download {"id":"' . $this->ids['public'] . '"} /-->' );

		$this->assertStringContainsString( 'The Manual', $rendered, 'The saved block renders its file.' );
	}

	/**
	 * And so does a saved listing block.
	 *
	 * @return void
	 */
	public function test_a_saved_listing_block_renders_through_the_block_parser() {
		$rendered = do_blocks( '<!-- wp:wp-downloadmanager/page-download /-->' );

		$this->assertStringContainsString( 'The Manual', $rendered, 'The saved block renders the listing.' );
	}
}
