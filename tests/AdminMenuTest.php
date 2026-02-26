<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use WackCloudfrontInvalidation\AdminMenu;

/**
 * AdminMenu クラスのテスト
 *
 * optionsSanitizeCallback のサニタイズ処理を検証する。
 *
 * optionsSanitizeCallback は内部で PluginSettings::getDryRunFromConstant() を呼ぶが、
 * テスト環境では WACK_CF_INV_DRY_RUN が未定義なので自然に null が返り、
 * 定数によるオーバーライドが発生しない。モックは不要。
 */
final class AdminMenuTest extends WP_Mock\Tools\TestCase
{
    //==========================================================================
    // distribution_id のサニタイズ
    //==========================================================================
    // phpcs:ignore
    public function test_optionsSanitizeCallback_sanitizes_distribution_id(): void
    {
        // distribution_id は sanitize_text_field() でサニタイズされる
        WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->with('E1234567890ABC')
            ->andReturn('E1234567890ABC');

        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'distribution_id' => 'E1234567890ABC',
        ]);

        $this->assertSame('E1234567890ABC', $result['distribution_id']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_distribution_id_not_in_result_when_missing(): void
    {
        // distribution_id キーが存在しない場合は結果に含まれない
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([]);

        $this->assertArrayNotHasKey('distribution_id', $result);
    }

    //==========================================================================
    // dry_run のサニタイズ
    //==========================================================================
    // phpcs:ignore
    public function test_optionsSanitizeCallback_dry_run_true_when_1_submitted(): void
    {
        // dry_run が '1' で送信された場合は true になる
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'dry_run' => '1',
        ]);

        $this->assertTrue($result['dry_run']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_dry_run_false_when_not_submitted(): void
    {
        // dry_run キーが送信されなかった場合 (チェックボックス未チェック) は false になる
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([]);

        $this->assertFalse($result['dry_run']);
    }

    //==========================================================================
    // invalidation_paths のサニタイズ
    //==========================================================================
    // phpcs:ignore
    public function test_optionsSanitizeCallback_splits_paths_by_newlines(): void
    {
        // 改行区切りのテキストが配列に変換される
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => "/posts/*\n/articles/*",
            ],
        ]);

        $this->assertSame(['/posts/*', '/articles/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_splits_paths_by_crlf(): void
    {
        // CRLF 改行にも対応している
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => "/posts/*\r\n/articles/*",
            ],
        ]);

        $this->assertSame(['/posts/*', '/articles/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_adds_leading_slash(): void
    {
        // 先頭スラッシュがないパスには自動で追加される
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => 'posts/*',
            ],
        ]);

        $this->assertSame(['/posts/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_trims_whitespace(): void
    {
        // パスの前後の空白は除去される
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => "  /posts/*  \n  /articles/*  ",
            ],
        ]);

        $this->assertSame(['/posts/*', '/articles/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_skips_empty_lines(): void
    {
        // 空行はスキップされる
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => "/posts/*\n\n/articles/*\n",
            ],
        ]);

        $this->assertSame(['/posts/*', '/articles/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_skips_post_type_with_empty_paths(): void
    {
        // パスが空の投稿タイプは結果に含まれない
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => '',
            ],
        ]);

        $this->assertArrayNotHasKey('post', $result['invalidation_paths'] ?? []);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_passes_through_already_array_paths(): void
    {
        // すでに配列になっているパス (sanitize_callback が複数回呼ばれる場合) はそのまま使用される
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => ['/posts/*', '/articles/*'],
            ],
        ]);

        $this->assertSame(['/posts/*', '/articles/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_handles_multiple_post_types(): void
    {
        // 複数の投稿タイプのパスを同時にサニタイズできる
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => '/posts/*',
                'news' => "/news/*\n/news/%slug%",
            ],
        ]);

        $this->assertSame(['/posts/*'], $result['invalidation_paths']['post']);
        $this->assertSame(['/news/*', '/news/%slug%'], $result['invalidation_paths']['news']);
    }
}
