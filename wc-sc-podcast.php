<?php
/*
Plugin Name: WooCommerce SoundCloud to Podast
Plugin URI: https://toiee.jp
Description: WooCommerce の Product, Member, Subscription と連動して動作するSoundCloud をPodcast にするためのプラグイン
Version: 0.2.1
Author: toiee Lab
Author URI: https://toiee.jp
License: GPL2
*/

/*  Copyright 2017 toiee Lab (email : desk@toiee.jp)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$wc_sc_podcast = new WC_SC_Podcast();

class WC_SC_Podcast
{
	private $options;
	
	function __construct()
	{
		
		// カスタム投稿タイプの追加
		add_action('init',  array( $this, 'create_post_type') );

		
		// --------- ショートコードの登録
		add_shortcode('scpodcast', array($this, 'add_scpodcast_shortcode'));


		// --------- カスタム投稿ページに、情報を追加
		add_filter( 'manage_scpcast_posts_columns', array($this, 'add_shortcode_fields') );
		add_action( 'manage_scpcast_posts_custom_column',  array($this, 'custom_shortcode_fields'), 10, 2 );

		add_action( 'add_meta_boxes', array($this, 'register_meta_boxes') );
		add_action( 'save_post', array($this, 'save_meta_boxes') );
		
		// --------- 設定のため --------------
		// メニューの追加
		add_action( 'admin_menu', array($this, 'add_plugin_page') );
		
		// 設定の初期化
		add_action('admin_init', array($this, 'page_init') );
		
		
		// --------- podcast 配信のため ----------
		add_action('wp_loaded', array( $this, 'podcast' ) );
		
	}
	
	
	function create_post_type()
	{
		register_post_type(
			'scpcast',
			array(
				'label' 				=> 'SC Podcast',
				'public'				=> true,
				'exclude_from_search'	=> false,
				'show_ui'				=> true,
				'show_in_menu'			=> true,
				'menu_position'			=> 5,
				'hierarchical'			=> false,
				'has_archive'			=> false,
				'supports'				=> array(
					'title',
				)
			)
		);
	}
	
	
	//------------------- shortcode
	function add_scpodcast_shortcode($atts)
	{
		$atts = shortcode_atts( array(
			'id' => '',
			'pro_id' => '',
			'sub_id' => '',
			'mem_id' => '',
			'type'   => 'url'
		), $atts );
		
		if( isset($atts['id']) && is_numeric($atts['id']) )
		{
			$uid	= get_current_user_id();
			$embed  = get_post_meta($atts['id'], 'wcscp_embedtag', true);
			
			$atts['usr_id'] = $uid;

			$atts_str = serialize( $atts );
			
			if($uid == 0){
				$token = '';
			}
			else{
				$token = $this->xor_encrypt( $atts_str, NONCE_KEY );
			}
			
			$type = isset( $atts['type'] ) ? $atts['type'] : '';
			
			$url = site_url().'/scpcast/?token='. rawurlencode( $token );
			
			
			
			switch($type)
			{
				case  'url':
					return '<p><input type="text" value="'.$url.'" style="width:100%" onclick="this.select();"></p>';
				case  'link':
					return '<p><a href="'.$url.'" target="_blank">'.$url.'</a></p>';
				default:
					return 'null';
//					return $embed;
				
			}
		}
	}
	
	
	
	//-------------------- 投稿ページ用
	
	/**
	* ショートコードを表示するためのコラムを追加
	*/
	function add_shortcode_fields( $columns )
	{
		$columns['shortcode'] = 'Shortcode';
		
		return $columns;
	}
	
	/**
	* ショートコードを表示するコラムに値を追加
	*/
	function custom_shortcode_fields( $column, $post_id )
	{
		switch ( $column )
		{
	        case 'shortcode' : 
    	        echo "<input type='text' value='[scpodcast id=\"{$post_id}\" type=\"url\"]' readonly='readonly' onclick='this.select();' />";
				break;
		}
	}
	
	/**
	* 投稿ページに「iframe を入れる」場所を作る
	*/
	function register_meta_boxes()
	{
		add_meta_box('wc_scp', 'WC SoundCloud Playlist to Podcast', array($this, 'display_embedtag_meta_box'), 'scpcast', 'advanced' );
		
		
	}
	function display_embedtag_meta_box( $post )
	{
		$id = get_the_ID();
		
		// embed
		$embed = get_post_meta($id, 'wcscp_embedtag', true);
		
		// type
		$casttype = get_post_meta($id, 'wcscp_casttype', true);
		$casttype = $casttype == '' ? 'audio-seminar' : $casttype;
		
		$select_type = array(
			'audio-seminar' => 'オーディオセミナー型',
			'podcast'		=> 'Podcast型',
			'campaign'		=> 'キャンペーン型'
		);
		
		
		// free type
		$freetype = get_post_meta($id, 'wcscp_freetype', true);
		$freetype = ($freetype == '') ? 'nolisten' : $freetype;
		
		$select_free = array(
			'nolisten' => '全く聞けない',
			'latest'   => '最新のみ',
		);
		
		// WooCommerce の制限情報（プロダクトID、サブスクリプションID、メンバーシップID）
		$wc_param = array('wcscp_product_ids'=>array(), 'wcscp_sub_ids'=>array(), 'wcscp_mem_ids'=>array());

		$wc_param_data = get_post_meta($id, 'wcscp_param', true);
		if( $wc_param_data != '' ){
			$wc_param_arr = unserialize( $wc_param_data );
			
			foreach($wc_param_arr as $key => $v)
			{
				$wc_param[$key] = implode(',', $v);
			}
		}
		
		wp_nonce_field( 'embedtag_meta_box', 'embedtag_meta_box_nonce' );

		echo <<<EOD
<p><b>閲覧制限</b></p>
<p>コンマ区切りで複数記入できます</p>
<table>
	<tr>
		<th><label>Product IDs</label></th>
		<td><input type="text" name="wcscp_product_ids" value="{$wc_param['wcscp_product_ids']}" /></td>
	</tr>
	<tr>
		<th><label>Subscription IDs</label></th>
		<td><input type="text" name="wcscp_sub_ids" value="{$wc_param['wcscp_sub_ids']}" /></td>
	</tr>
	<tr>
		<th><label>Membership IDs</label></th>
		<td><input type="text" name="wcscp_mem_ids" value="{$wc_param['wcscp_mem_ids']}" /></td>
	</tr>
</table>
<p><b>Embed code</b></p>
<p>SoundCloudのプレイリストのEmbed code (リンクではなく、iframeタグです)を貼り付けてください。<a href="https://github.com/toiee-lab/wc-sc-podcast/raw/master/embed.png" target="_blank">(詳細はこちら)</a></p>
<textarea name="wcscp_embedtag" style="width:100%;border:2px solid #666;height:5em;">
{$embed}
</textarea>

<p><b>Podcastタイプを選択</b></p>
<p>Podcastのタイプを選んでください。音声セミナー型は、SoundCloudのプレイリスト順に従い、最新投稿をプレイリストの１番目として設定します。Podcast型はプレイリスト内のオーディオの日付でPodcastを作成します。キャンペーン型は、音声セミナー型でユーザーの登録日時から計算して、１日１つずつオーディオを配信します。</p>
<select name="wcscp_casttype">
EOD;

		foreach( $select_type as $key => $disp)
		{
			$selected = ($key == $casttype) ? 'selected' : '';
			echo "<option value='{$key}' {$selected}>{$disp}</option>";
		}


		echo <<<EOD
</select>

<p><b>無料ユーザーの扱い</b></p>
<p>「全く聞けない」場合は、完全にオーディオデータをなくします。「最新のみ」は、音声セミナー型の場合は第一話を、Podcast型の場合は最新話をお聞きいただけます。</p>
<select name="wcscp_freetype">
EOD;
		
		foreach( $select_free as $key => $disp)
		{
			$selected = ($key == $freetype) ? 'selected' : '';
			echo "<option value='{$key}' {$selected}>{$disp}</option>";
		}
	

		echo <<<EOD
</select>


EOD;
	}
	function save_meta_boxes($post_id)
	{
        // Check if our nonce is set.
        if ( ! isset( $_POST['embedtag_meta_box_nonce'] ) ) {
            return $post_id;
        }
 
        $nonce = $_POST['embedtag_meta_box_nonce'];
 
        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'embedtag_meta_box' ) ) {
            return $post_id;
        }
		
		
		//embed タグの iframe 部分だけを取り出す
		$embed = isset($_POST['wcscp_embedtag']) ? $_POST['wcscp_embedtag'] : null;
		preg_match('/(<iframe.*?<\/iframe>)/s', $embed, $matches);
		$embed = $matches[1];
		
		$embed_before = get_post_meta($post_id, 'wcscp_embedtag', true);
		if($embed)
		{
			update_post_meta($post_id, 'wcscp_embedtag', $embed);
		}
		else
		{
			delete_post_meta($post_id, 'wcscp_embedtag', $embed_before);
		}
		
		
		//
		$casttype = isset($_POST['wcscp_casttype']) ? $_POST['wcscp_casttype'] : 'audio-seminar';
		update_post_meta($post_id, 'wcscp_casttype', $casttype);
		
		$freetype = isset($_POST['wcscp_freetype']) ? $_POST['wcscp_freetype'] : 'nolisten';
		update_post_meta($post_id, 'wcscp_freetype', $freetype);
		
		// product_ids, sub_ids, mem_ids を保存する
		$wc_param = array();
		foreach( array('product', 'sub', 'mem') as $name )
		{
			$vname = 'wcscp_'.$name.'_ids';
			if( isset( $_POST[$vname]) )
			{
				$wc_param[$vname] = explode(',', $_POST[$vname]);
			}
		}
		update_post_meta( $post_id, 'wcscp_param', serialize($wc_param) );
	}

	
	
	
	//-------------------- 設定用
	
	//設定を保存する	
    public function add_plugin_page()
    {
        // add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
        //   $page_title: 設定ページの<title>部分
        //   $menu_title: メニュー名
        //   $capability: 権限 ( 'manage_options' や 'administrator' など)
        //   $menu_slug : メニューのslug
        //   $function  : 設定ページの出力を行う関数
        //   $icon_url  : メニューに表示するアイコン
        //   $position  : メニューの位置 ( 1 や 99 など )
 
        // 設定のサブメニューとしてメニューを追加する場合は下記のような形にします。
        add_options_page( 'WC SCPodcast', 'WC SCPodcast', 'manage_options', 'wcscp_setting', array( $this, 'create_admin_page' ) );
    }
	
	
	    public function page_init()
    {
        // 設定を登録します(入力値チェック用)。
        // register_setting( $option_group, $option_name, $sanitize_callback )
        //   $option_group      : 設定のグループ名
        //   $option_name       : 設定項目名(DBに保存する名前)
        //   $sanitize_callback : 入力値調整をする際に呼ばれる関数
        register_setting( 'wcscp_setting', 'wcscp_setting', array( $this, 'sanitize' ) );
 
        // 入力項目のセクションを追加します。
        // add_settings_section( $id, $title, $callback, $page )
        //   $id       : セクションのID
        //   $title    : セクション名
        //   $callback : セクションの説明などを出力するための関数
        //   $page     : 設定ページのslug (add_menu_page()の$menu_slugと同じものにする)
        add_settings_section( 'wcscp_setting_section_id', '', '', 'wcscp_setting' );
 
        // 入力項目のセクションに項目を1つ追加します(今回は「メッセージ」というテキスト項目)。
        // add_settings_field( $id, $title, $callback, $page, $section, $args )
        //   $id       : 入力項目のID
        //   $title    : 入力項目名
        //   $callback : 入力項目のHTMLを出力する関数
        //   $page     : 設定ページのslug (add_menu_page()の$menu_slugと同じものにする)
        //   $section  : セクションのID (add_settings_section()の$idと同じものにする)
        //   $args     : $callbackの追加引数 (必要な場合のみ指定)
        add_settings_field( 'sc_cid', 'メッセージ', array( $this, 'sc_cid_callback' ), 'wcscp_setting', 'wcscp_setting_section_id' );
    }
    
    public function create_admin_page()
    {
        // 設定値を取得します。
        $this->options = get_option( 'wcscp_setting' );
        ?>
        <div class="wrap">
            <h2>SoundCloud CID設定</h2>
            <?php
            // add_options_page()で設定のサブメニューとして追加している場合は
            // 問題ありませんが、add_menu_page()で追加している場合
            // options-head.phpが読み込まれずメッセージが出ない(※)ため
            // メッセージが出るようにします。
            // ※ add_menu_page()の場合親ファイルがoptions-general.phpではない
            global $parent_file;
            if ( $parent_file != 'options-general.php' ) {
                require(ABSPATH . 'wp-admin/options-head.php');
            }
            ?>
            <form method="post" action="options.php">
            <?php
                // 隠しフィールドなどを出力します(register_setting()の$option_groupと同じものを指定)。
                settings_fields( 'wcscp_setting' );
                // 入力項目を出力します(設定ページのslugを指定)。
                do_settings_sections( 'wcscp_setting' );
                // 送信ボタンを出力します。
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }
    
    
     public function sc_cid_callback()
    {
        // 値を取得
        $sc_cid = isset( $this->options['sc_cid'] ) ? $this->options['sc_cid'] : '';
        // nameの[]より前の部分はregister_setting()の$option_nameと同じ名前にします。
        ?><input type="text" id="sc_cid" name="wcscp_setting[sc_cid]" value="<?php esc_attr_e( $sc_cid ) ?>" /><?php
    }
	
    /**
     * 送信された入力値の調整を行います。
     *
     * @param array $input 設定値
     */
    public function sanitize( $input )
    {
        // DBの設定値を取得します。
        $this->options = get_option( 'wcscp_setting' );
 
        $new_input = array();
 
        // メッセージがある場合値を調整
        if( isset( $input['sc_cid'] ) && trim( $input['sc_cid'] ) !== '' ) {
            $new_input['sc_cid'] = sanitize_text_field( $input['sc_cid'] );
        }
        // メッセージがない場合エラーを出力
        else {
            // add_settings_error( $setting, $code, $sc_cid, $type )
            //   $setting : 設定のslug
            //   $code    : エラーコードのslug (HTMLで'setting-error-{$code}'のような形でidが設定されます)
            //   $sc_cid : エラーメッセージの内容
            //   $type    : メッセージのタイプ。'updated' (成功) か 'error' (エラー) のどちらか
            add_settings_error( 'wcscp_setting', 'sc_cid', 'メッセージを入力して下さい。' );
 
            // 値をDBの設定値に戻します。
            $new_input['sc_cid'] = isset( $this->options['sc_cid'] ) ? $this->options['sc_cid'] : '';
        }
 
        return $new_input;
    }
 
 
	function xor_encrypt($plaintext, $key){

        $len = strlen($plaintext);
        $enc = "";
        for($i = 0; $i < $len; $i++){
                $asciin = ord($plaintext[$i]);
                $enc .= chr($asciin ^ ord($key[$i]));
        }
        $enc = base64_encode($enc);
        return $enc;
	}
	
	function xor_decrypt($encryptedText, $key){
        $enc = base64_decode($encryptedText);
        $plaintext = "";
        $len = strlen($enc);
        for($i = 0; $i < $len; $i++){
                $asciin = ord($enc[$i]);
                $plaintext .= chr($asciin ^ ord($key[$i]));
        }
        return $plaintext;
	}	
 
 
	function podcast()
	{
		if( preg_match( '|^/scpcast/\?token=.*|', $_SERVER['REQUEST_URI'] ) )
		{
			require_once dirname(__FILE__).'/sc2podcast.php';
			
			$str = $this->xor_decrypt($_GET['token'], NONCE_KEY);			
			$atts = unserialize($str);
			
			if( !isset($atts['id']) ){   // id がなければ、動作させない
				echo "Error: invalid token";
				exit;
			}
			
			$uid = $atts['usr_id'];  // user id
			$pid = $atts['id'];      // scpodcast post_id

			$embed    = get_post_meta($pid, 'wcscp_embedtag', true);
			$casttype = get_post_meta($pid, 'wcscp_casttype', true);
			$freetype = get_post_meta($pid, 'wcscp_freetype', true);
			
			$wc_param = unserialize( get_post_meta($pid, 'wcscp_param', true) );
			
			$user = get_userdata( $uid );
			$email = $user->data->user_email;
			$registered_date = strtotime( $user->data->user_registered );
			
			$cid = get_option('wcscp_setting', false);
			$cid = $cid['sc_cid'];
			$base_url = site_url().'/wp-content/uploads/sc2podcast/';

			$sc2p = new SC2Podcast($cid, $embed, $base_url);			


			// WooCommerce でチェックする
			$has_access = false;  // Access許可
			
			// Super user の場合は出力する
			if( is_super_admin() ){
				output_podcast( $casttype, $email, $registered_date);
				exit;
			}
			
			$wc_param = false;
			
			// 商品でチェックする
			$pro_ids = explode(',', $wc_param['wcscp_product_ids']);
			foreach($pro_ids as $i)
			{
				$access = ($i != '') ? wc_customer_bought_product( $email, $uid, $i ) : false;
				if($access){
					output_podcast( $casttype, $email, $registered_date);
					exit;
				}
			}
			
			
			// Subscription でチェックをする 
			if ( function_exists('wcs_user_has_subscription') )
			{
				$sub_ids = explode(',', $wc_param['wcscp_sub_ids']);

				foreach( $sub_ids as $i )
				{
					$access = ($i != '') ? wcs_user_has_subscription( $uid, $i, 'active') : false;
					if( $access ){
						output_podcast( $casttype, $email, $registered_date);
						exit;
					}
				}
			}

			// Membership でチェックする
			if ( function_exists( 'wc_memberships' ) )
			{
				$mem_ids = explode(',', $wc_param['wcscp_mem_ids']);
				
				foreach( $mem_ids as $i )
				{
					$access = wc_memberships_is_user_active_member(  $uid, $i );
					if( $access ){
						output_podcast( $casttype, $email, $registered_date);
						exit;						
					}
				}
			}
						
			// アクセス権がない場合
			switch( $freetype )
			{
				case 'nolisten':
					//オーディオのURLを全部書き換えるようにする
					$sc2p->output_as_free_nolisten($email, $casttype);
					exit;
				
				case 'latest':
					//常に最新話のオーディオ以外のURLを書き換える
					$sc2p->output_as_free_listen($email, $casttype);
					exit;
					
				default:
					header("Content-Type:text/plain; charset=utf-8");
					echo "L463 : not access rcp_user_can_access({$uid}, {$pid}) \n";
					var_dump($casttype, $freetype);
					exit;
			}
		}
	}
	
	function output_podcast( $casttype, $email, $registered_date)
	{
		switch( $casttype )
		{
			case 'audio-seminar' :
				$sc2p->output_as_audioseminar($email);
				break;
			case 'podcast' :
				$sc2p->output($email);
				break;
			case 'campaign' :
				$sc2p->output_as_campaign($email, $registered_date);
				break;
			default:
				header("Content-Type:text/plain; charset=utf-8");
				echo "L544 : pass user_can_access({$uid}, {$pid}) \n";
				exit;
		}
	}
 
 
}	
