<?php
/**
 * Plugin Name:     Boolick Post
 * Plugin URI:      https://github.com/fumikito/boolick-post
 * Description:     Parse Booth's CSV and convert it to click post ready CSV.
 * Author:          Takahashi Fumiki
 * Author URI:      https://takahashifumiki.com
 * Version:         1.0.0
 *
 * @package         boolick-post
 */

defined( 'ABSPATH' ) || die();

// Register javascripts.
add_action( 'init', function() {
	wp_register_script( 'boolick-post', plugin_dir_url(  __FILE__ ) . 'boolick-post.js', [ 'jquery', 'uikit' ], filemtime( __DIR__ . '/boolick-post.js' ), true );
	wp_localize_script( 'boolick-post', 'BoolickPost', [
		'endpoint' => rest_url( 'boolick-post/v1' ),
	] );
} );

// Register short code for CSV converter.
add_shortcode( 'boolick-post', function( $attr = [], $content = '' ) {
	if ( is_singular() ) {
		wp_enqueue_script( 'boolick-post' );
	}
	$endpoint = esc_url( rest_url( 'boolick-post/v1/csv' ) );
	$out = <<<HTML
		<form id="booclick-post-uploader" class="sans uk-margin-top uk-margin-bottom" method="post" enctype="multipart/form-data" action="{$endpoint}" target="_blank">
			<div class="js-upload uk-placeholder uk-text-center">
    			<span uk-icon="icon: cloud-upload"></span>
    			<span class="uk-text-middle">ここにBoothの注文CSVをドラッグ＆ドロップするか</span>
    			<div uk-form-custom>
        			<input type="file" accept="text/csv" name="clickpost" />
        			<span class="uk-link">選択</span>
    			</div>
    			<span class="uk-text-middle">してください。</span>
			</div>
		</form>
HTML;
	return implode( "\n", array_map( 'trim', explode( "\n", $out ) ) );
} );

// Register short code for transactional message.
add_shortcode( 'boolick-post-text', function( $attr = [], $content = '' ) {
	if ( is_singular() ) {
		wp_enqueue_script( 'boolick-post' );
	}
	$out = <<<HTML
		<div class="uk-margin-top uk-margin-bottom sans boolick-text-container">
			<textarea class="uk-textarea boolick-text-input" placeholder="ここにClick Postの「マイページ」のデータを貼り付けてください。"></textarea>
			<p class="uk-text-center uk-margin-small-top">
				<button class="uk-button uk-button-primary boolick-text-button">変換する</button>
			</p>
			<ol class="boolick-text-list uk-list uk-list-striped">
				<li><pre>ここにテキストが表示されます。</pre></li>
			</ol>
		</div>
HTML;
	return implode( "\n", array_map( 'trim', explode( "\n", $out ) ) );
} );

/**
 * Add rest endpoint
 */
add_action( 'rest_api_init', function() {
	register_rest_route( 'boolick-post/v1', 'csv', [
		[
			'methods' => 'POST',
			'callback' => function( WP_REST_Request $request ) {
				try {
					if ( ! ( isset( $_FILES[ 'clickpost' ] ) && UPLOAD_ERR_OK === $_FILES[ 'clickpost' ][ 'error' ] ) ) {
						throw new Exception( 'ファイルが指定されていません。', 400 );
					}
					$file = $_FILES[ 'clickpost' ];
					if ( ! preg_match( '/\.csv$/u', $file['name'] ) ) {
						throw new Exception( 'ファイルがCSVではありません。', 400 );
					}
					$rows = [ [
						'お届け先郵便番号',
						'お届け先氏名',
						'お届け先敬称',
						'お届け先住所1行目',
						'お届け先住所2行目',
						'お届け先住所3行目',
						'お届け先住所4行目',
						'内容品',
					] ];
					$file = new SplFileObject( $file['tmp_name'] );
					$file->setFlags( SplFileObject::READ_CSV );
					foreach ( $file as $line ) {
						// Skip if order number is not set.
						if ( ! isset( $line[0] ) || ! preg_match( '/\d+/', $line[0] ) ) {
							continue;
						}
						$item_name = explode( '/', $line[14] );
						$rows[] = [
							$line[8],
							$line[12],
							'様',
							$line[9],
							$line[10],
							$line[11],
							'',
							trim( $item_name[ count( $item_name ) - 1 ] ),
						];
					}
					if ( 2 > count( $rows ) ) {
						throw new Exception( 'CSVを作成できませんでした。CSVは文字コードUTF-8で改行コードLFでなければなりません。', 400 );
					}
					header( 'Content-type: application/octet-stream' );
					header( sprintf( 'Content-Disposition: attachment; filename="booth-%s.csv"', date_i18n( 'YmdHis' ) ) );
					$counter = 0;
					echo implode( "\r\n", array_map( function( $row ) use ( &$counter ) {
						$counter++;
						return implode( ",", array_map( function( $cell ) use ( &$counter ) {
							$cell = str_replace( '"', '\\"', $cell );
							if ( 1 < $counter ) {
								$cell = sprintf( '"%s"', $cell );
							}
							return mb_convert_encoding( $cell, 'sjis-win', 'utf-8' );
						}, $row ) );
					}, $rows ) );
					die();
				} catch ( Exception $e ) {
					return new WP_Error( 'invalid_file', $e->getMessage(), [
						'response' => $e->getCode(),
						'status'   => $e->getCode(),
					] );
				}
			},
		]
	] );
} );
