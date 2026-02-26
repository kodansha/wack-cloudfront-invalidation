<?php

// First we need to load the composer autoloader, so we can use WP Mock
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap WP_Mock to initialize built-in features
WP_Mock::bootstrap();

// テスト用の WP_Post スタブを定義
if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;
        public string $post_author = '';
        public string $post_type = '';
        public string $post_title = '';
        public string $post_status = '';
        public string $post_name = '';
        public string $post_content = '';
        public string $post_excerpt = '';
        public int $post_parent = 0;
    }
}
