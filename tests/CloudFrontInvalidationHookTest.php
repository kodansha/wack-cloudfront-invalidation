<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use WP_Post;
use WackCloudfrontInvalidation\CloudFrontInvalidationHook;

/**
 * CloudFrontInvalidationHook クラスのテスト
 *
 * replacePlaceholder と shouldSkip のプライベートメソッドを
 * ReflectionMethod 経由で直接テストする。
 *
 * DOING_AUTOSAVE / REST_REQUEST 定数に依存するテストは、
 * WP_Mock\Tools\TestCase と @runInSeparateProcess の組み合わせが
 * PHP 8.5 との互換性問題を抱えるため省略している。
 */
final class CloudFrontInvalidationHookTest extends WP_Mock\Tools\TestCase
{
    /**
     * WP_Post スタブを生成する
     */
    private function makePost(int $id, string $post_type, string $post_status, string $post_name = ''): WP_Post
    {
        $post = new WP_Post();
        $post->ID = $id;
        $post->post_type = $post_type;
        $post->post_status = $post_status;
        $post->post_name = $post_name;

        return $post;
    }

    /**
     * プライベートメソッドを呼び出すヘルパー
     *
     * PHP 8.1 以降 setAccessible() は不要 (no-op) なため呼び出さない
     */
    private function callPrivate(object $object, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);

        return $reflection->invoke($object, ...$args);
    }

    //==========================================================================
    // replacePlaceholder
    //==========================================================================
    // phpcs:ignore
    public function test_replacePlaceholder_id_placeholder(): void
    {
        // %id% が投稿 ID に置換される
        $post = $this->makePost(42, 'post', 'publish', 'my-slug');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%id%', $post);

        $this->assertSame('/articles/42', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_slug_placeholder(): void
    {
        // %slug% が投稿のスラッグ (post_name) に置換される
        $post = $this->makePost(42, 'post', 'publish', 'my-awesome-post');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%slug%', $post);

        $this->assertSame('/articles/my-awesome-post', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_slug_fallback_to_id_when_post_name_empty(): void
    {
        // post_name が空の場合は %slug% が投稿 ID にフォールバックする
        $post = $this->makePost(42, 'post', 'publish', '');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%slug%', $post);

        $this->assertSame('/articles/42', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_no_placeholder(): void
    {
        // プレースホルダーがないパスはそのまま返される
        $post = $this->makePost(42, 'post', 'publish', 'my-slug');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/static/path/*', $post);

        $this->assertSame('/static/path/*', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_both_placeholders(): void
    {
        // %id% と %slug% が同時に含まれる場合はどちらも置換される
        $post = $this->makePost(42, 'post', 'publish', 'my-slug');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%id%/%slug%', $post);

        $this->assertSame('/articles/42/my-slug', $result);
    }

    //==========================================================================
    // shouldSkip - 投稿ステータスによるスキップ判定
    //==========================================================================
    // phpcs:ignore
    public function test_shouldSkip_auto_draft_status(): void
    {
        // auto-draft ステータスはスキップ対象
        $post = $this->makePost(1, 'post', 'auto-draft');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_draft_status(): void
    {
        // draft ステータスはスキップ対象
        $post = $this->makePost(1, 'post', 'draft');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_inherit_status(): void
    {
        // inherit ステータスはスキップ対象
        $post = $this->makePost(1, 'post', 'inherit');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_pending_status(): void
    {
        // pending ステータスはスキップ対象
        $post = $this->makePost(1, 'post', 'pending');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    //==========================================================================
    // shouldSkip - publish ステータスの各条件
    //==========================================================================
    // phpcs:ignore
    public function test_shouldSkip_false_for_normal_published_post(): void
    {
        // 通常の published 投稿はスキップしない
        $post = $this->makePost(1, 'post', 'publish', 'my-post');
        $hook = new CloudFrontInvalidationHook();

        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(false);
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(false);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_autosave_post(): void
    {
        // wp_is_post_autosave が truthy を返す場合はスキップ
        $post = $this->makePost(1, 'post', 'publish', 'my-post');
        $hook = new CloudFrontInvalidationHook();

        // autosave の場合は親投稿 ID (truthy) を返す
        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(100);
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(false);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_revision_post(): void
    {
        // wp_is_post_revision が truthy を返す場合はスキップ
        $post = $this->makePost(1, 'post', 'publish', 'my-post');
        $hook = new CloudFrontInvalidationHook();

        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(false);
        // リビジョンの場合は親投稿 ID (truthy) を返す
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(100);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }
}
