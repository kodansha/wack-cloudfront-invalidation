<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use WackCloudfrontInvalidation\Utility;

/**
 * Utility クラスのテスト
 */
final class UtilityTest extends WP_Mock\Tools\TestCase
{
    //==========================================================================
    // getPostTypes
    //==========================================================================
    // phpcs:ignore
    public function test_getPostTypes_filters_default_post_types(): void
    {
        // page / attachment / revision / nav_menu_item などのデフォルト投稿タイプは除外される
        WP_Mock::userFunction('get_post_types')
            ->once()
            ->with(['public' => true], 'objects')
            ->andReturn([
                'page'         => (object) ['name' => 'page', 'label' => 'Pages'],
                'attachment'   => (object) ['name' => 'attachment', 'label' => 'Media'],
                'revision'     => (object) ['name' => 'revision', 'label' => 'Revisions'],
                'nav_menu_item' => (object) ['name' => 'nav_menu_item', 'label' => 'Navigation Menu Items'],
                'wp_template'  => (object) ['name' => 'wp_template', 'label' => 'Templates'],
                'wp_template_part' => (object) ['name' => 'wp_template_part', 'label' => 'Template Parts'],
            ]);

        $result = Utility::getPostTypes();

        $this->assertEmpty($result, 'デフォルト投稿タイプはすべて除外される');
    }

    // phpcs:ignore
    public function test_getPostTypes_keeps_custom_post_types(): void
    {
        // カスタム投稿タイプはフィルタリングされずに残る
        WP_Mock::userFunction('get_post_types')
            ->once()
            ->with(['public' => true], 'objects')
            ->andReturn([
                'page' => (object) ['name' => 'page', 'label' => 'Pages'],
                'news' => (object) ['name' => 'news', 'label' => 'News'],
                'book' => (object) ['name' => 'book', 'label' => 'Books'],
            ]);

        $result = Utility::getPostTypes();
        $names = array_map(fn($pt) => $pt->name, $result);

        $this->assertNotContains('page', $names, 'page はデフォルト投稿タイプなので除外される');
        $this->assertContains('news', $names, 'カスタム投稿タイプ news は残る');
        $this->assertContains('book', $names, 'カスタム投稿タイプ book は残る');
    }

    // phpcs:ignore
    public function test_getPostTypes_keeps_post_type(): void
    {
        // 'post' 投稿タイプはデフォルト除外リストに入っておらず残る
        WP_Mock::userFunction('get_post_types')
            ->once()
            ->with(['public' => true], 'objects')
            ->andReturn([
                'post' => (object) ['name' => 'post', 'label' => 'Posts'],
                'page' => (object) ['name' => 'page', 'label' => 'Pages'],
            ]);

        $result = Utility::getPostTypes();
        $names = array_map(fn($pt) => $pt->name, $result);

        $this->assertContains('post', $names, "'post' 投稿タイプはフィルタ対象外なので残る");
        $this->assertNotContains('page', $names, "'page' は除外される");
    }

    // phpcs:ignore
    public function test_getPostTypes_returns_empty_when_no_post_types(): void
    {
        // 取得した投稿タイプが空の場合は空配列を返す
        WP_Mock::userFunction('get_post_types')
            ->once()
            ->with(['public' => true], 'objects')
            ->andReturn([]);

        $result = Utility::getPostTypes();
        $this->assertEmpty($result);
    }

    //==========================================================================
    // infoLog
    //==========================================================================
    // phpcs:ignore
    public function test_infoLog_does_not_throw(): void
    {
        // logger() 関数が存在しない環境では error_log() へフォールバックする
        // 例外なく実行されることを確認する
        $this->expectNotToPerformAssertions();
        Utility::infoLog('Test info message');
    }

    //==========================================================================
    // errorLog
    //==========================================================================
    // phpcs:ignore
    public function test_errorLog_does_not_throw(): void
    {
        // logger() 関数が存在しない環境では error_log() へフォールバックする
        // 例外なく実行されることを確認する
        $this->expectNotToPerformAssertions();
        Utility::errorLog('Test error message');
    }
}
