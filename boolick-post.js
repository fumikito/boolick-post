/**
 * Description
 */

/*global BoolickPost: true*/

BoolickPost.callback = function() {

};

jQuery( document ).ready( function ($) {

  'use strict';

  /**
   *
   */
  var handleEvent = function( action, label, value ) {
    try {
      gtag( 'event', action, {
        'event_category': 'boolick-post',
        'event_label': label,
        'value': value || 1
      });
    } catch (err) {
      // Do nothing.
    }
  };

  // Progress bar.
  $( 'input[name=clickpost]' ).on( 'change', function( e ) {
    var $form = $( this ).parents( 'form' );
    var fileName = $(this).val();
    if ( ! fileName || ! /\.csv$/.test( fileName ) ) {
      alert( 'ファイルはCSVではないといけません。' );
      return;
    }
    $form.submit();
    setTimeout( function() {
      $form.reset();
    }, 100 );
    handleEvent( 'csv', 'CSVアップロード' );
  } );

  // Text converter.
  $( '.boolick-text-button' ).click( function( e ) {
    e.preventDefault();
    var $container = $( this ).parents( '.boolick-text-container' );
    var $ol = $container.find( 'ol' );
    var lines = $container.find( 'textarea' ).val().split( /(\r|\n)/ ).map( $.trim ).filter(function( line ) {
      return -1 < line.indexOf( "\t" )
    } ).map( function( line ) {
      var cell = line.split( "\t" ).map( $.trim );
      var num  = cell[1];
      var name = cell[2];
      var item = cell[3];
      return num + "\n\n" + name + "様\nご注文ありがとうございます。商品 " + item + " を発送いたしました。以下のURLより発送状況をご確認いただけます。\n" +
        "https://trackings.post.japanpost.jp/services/srv/search/direct?searchKind=S002&locale=ja&reqCodeNo1=%22" + num + "%22"
    } );
    if ( ! lines.length ) {
      alert( '変換に失敗しました。コピペをやりなおしてください。' );
      return;
    }
    $ol.empty();
    lines.map( function( line ) {
      $ol.append( $( '<li></li>' ).append( $( '<pre></pre>' ).text( line ) ).append( '<button class="uk-button uk-button-default">削除</button>' ) );
    } );
    handleEvent( 'text', 'テキストコンバート', lines.length );
  } );

  $( '.boolick-text-list' ).on( 'click', 'button', function( e ) {
    e.preventDefault();
    $( this ).parents( 'li' ).remove();
  } );
});
