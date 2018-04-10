<?php
/**
* 
* 
* 
*/

class SC2Podcast
{
	
	var $cid;
	var $iframe;
	var $sc_playlist_url;
	
	var $playlist; //playlist の id
	var $secretkey; //playlist 固有のsecret key

	var $feed_conf;
	var $trakcs;
	
	var $path;
	var $file_url_path;

	
	/**
	* $cid : SoundCloud のアプリ用のシークレットキー
	* $iframe : Soundcloudで作成したプレイリストのembedタグ（iframe）
	* $options : 変更したいパラメータがあれば使う
	*/
	function __construct( $cid, $iframe , $file_url_path, $path = null)
	{
		
		$this->file_url_path = rtrim($file_url_path, '/').'/';
		
		//SoundCloudのPlaylistのURL（たぶん、変わることはないだろう）
		$this->sc_playlist_url = 'https://api.soundcloud.com/playlists/';
		$this->cid = $cid;
		$this->iframe = $iframe;
		
		preg_match('|api.soundcloud.com/playlists/([0-9]+)|', $iframe, $matches);
		$this->playlist = $matches[1];
			
		preg_match('|secret_token%3D(.*?)&|', $iframe, $matches);
		$this->secretkey = $matches[1];
		
		
		
		//SoundCloudのプレイリストから、様々なデータと取り出す
		$url = $this->sc_playlist_url.$this->playlist.'?client_id='.$this->cid.'&secret_token='.$this->secretkey;
		$ret = json_decode( file_get_contents($url), true);

		
		//プレイリストの一覧を tracks に格納する
		$this->tracks = array();
		foreach($ret['tracks'] as $t)
		{
			$this->tracks[ $t['id'] ] = $t;
		}
		
		unset( $ret['tracks'] );
		
		//Feedの情報を設定
		$this->feed_conf = array(
			'link'	 	    => $ret['user']['permalink_url'],   //セールスページを設定しよう
			'email'         => 'example@example.com',      // toiee のでいいだろう
			'author'   		=> $ret['user']['username'],          // よほどでない限り変えなくていい
			'explicit' 		=> 'No',
			'block'    		=> 'yes',
			'ttl'      		=> '86400',
			'lang'     		=> 'ja',
			
			'title'  	    => $ret['title'],
			'category'      => $ret['genre'],
			'subcategory'   => '',
			'description'   => $ret['description'],
			'copyright'     => $ret['license'],
			
			'date'          => strtotime($ret['last_modified']),
			'pubDate'       => date("D, d M Y H:i:s O", strtotime($ret['last_modified']) ),
		);

		// アートワークの設定
		if( $ret['artwork_url'] != NULL )
		{
			
			$artw_url = str_replace('large.jpg', 'original.jpg', $ret['artwork_url']);
			
			if( $fp = @fopen($artw_url, "r") ){
				fclose($fp);	
			}
			else
			{
				$artw_url = str_replace('original.jpg', 'original.png', $artw_url);				
			}
						
			$this->feed_conf['image'] = $artw_url;
		}
		else
		{
			$this->feed_conf['image'] = 'https://dummyimage.com/1400x1400/cccccc/ffffff.jpg&text='.rawurlencode($ret['title']);
		}
		
		
		// ファイルの保存先の決定
		if( $path == null)
		{
			//WordPress を前提に書く
			$this->path = dirname( dirname( dirname(__FILE__) )) . '/uploads/sc2podcast/'.$this->playlist.'/';

			if( !file_exists($this->path) )
			{
				if( ! mkdir($this->path, 0777, true) )
				{
					die('Failed to create folders...');
				}
			}
		}
		else
		{
			$this->path = $path;
		}
		
	
		$this->download_files();
		
	}
	
	
	
	function download_files()
	{
		foreach( $this->tracks as $track_id => $track )
		{
			$path = $this->path.'/'.$track_id.'.mp3';
			$t_sc = strtotime( $track['last_modified']);
			
		//	echo "[".$path."] local:soundcloud = ".filemtime($path).":".$t_sc."\n";
		
			if( file_exists($path) &&
				(filemtime($path) >= strtotime( $track['last_modified']))
			)
			{
		//		echo "Do nothing.\n";
			}
			else{
				$stream_url = 'https://api.soundcloud.com/tracks/'.$track_id.'/stream?client_id='.$this->cid.'&secret_token='.$this->secretkey;
				file_put_contents($path, file_get_contents($stream_url));
				
				//id3 タグを設定する	 (mp3info コマンドを使う)
		/*
				$year = date("Y", strtotime($track['last_modified']));
						
				$opt =   ' -a "'.$conf['feed_auther'].'"'
						.' -g 12'
						.' -l "'.$ret['title'].'"'
						.' -t "'.$track["title"].'"'
						.' -y '.$year;
				exec('/usr/bin/mp3info '.$opt.' '.$path);
		*/
				touch($path, $t_sc);
			}
		}
	}
	
	
	// オーディオセミナーとして
	function output_as_audioseminar($user_email='')
	{
		
		$s_date = $this->feed_conf['date'];
		$cnt = 0;
		
		foreach($this->tracks as $key=>$track)
		{
			$this->tracks[$key]['created_at'] = date("Y/m/d h:m:s +0000", $s_date);
			$this->tracks[$key]['episode_num'] = ++$cnt;
			$s_date -= (60*60*24*2);
		}
		
		$this->output($user_email);
	}
	
	
	// キャンペーンとして
	// $s_date は、タイムスタンプ
	function output_as_campaign($user_email='', $s_date)
	{
		// オーディオセミナーとして、並び替えをする
		$cnt = 0;
		
		foreach($this->tracks as $key=>$track)
		{
			$this->tracks[$key]['created_at'] = date("Y/m/d h:m:s +0000", $s_date);
			$this->tracks[$key]['episode_num'] = ++$cnt;
			$s_date += (60*60*24);
		}
		
		$this->output($user_email);
	}
	
	// 全く聞けない状態
	function output_as_free_nolisten($user_email='', $casttype)
	{
		foreach($this->tracks as $key=>$track)
		{
			$this->tracks[$key]['id'] = 'dummy';
		}
		
		switch($casttype)
		{
			case 'podcast':
				$this->output( $user_email );
			default :
				$this->output_as_audioseminar( $user_email );
				break;
		}
	}
	
	function output_as_free_listen( $user_email, $casttype )
	{	
		
		switch($casttype)
		{
			case 'podcast':
				// 日付で並び替えをする
				usort($this->tracks, function($a, $b){		
					$t_a = strtotime($a['created_at']);
					$t_b = strtotime($b['created_at']);
					return $t_a - $t_b;
					});
			
				// 末尾のid(ファイル名)を退避
				end($this->tracks);
				$head_key = key( $this->tracks );
				$id   = $this->tracks[$head_key]['id'];
				
				// dummy で置き換え
				foreach($this->tracks as $key=>$track)
				{
					$this->tracks[$key]['id'] = 'dummy';
				}				
				
				// 先頭を元に戻す
				$this->tracks[$head_key]['id'] = $id;
				
				$this->output_as_audioseminar( $user_email );
				break;
			
			
				$this->output( $user_email );
				break;
				
			default :
			
				// 先頭のid(ファイル名)を退避
				$head_key = key( $this->tracks );
				$id   = $this->tracks[$head_key]['id'];
				
				// dummy で置き換え
				foreach($this->tracks as $key=>$track)
				{
					$this->tracks[$key]['id'] = 'dummy';
				}				
				
				// 先頭を元に戻す
				$this->tracks[$head_key]['id'] = $id;
				
				$this->output_as_audioseminar( $user_email );
				break;
		}
	
	
	}
	
	
	function output($user_email='')
	{
		$feed = $this->feed_conf;
		$tracks = $this->tracks;
		$sec_key = $this->secretkey;
		
		// 日付が新しいものほど、下に来るようにならべかえる
		usort($tracks, function($a, $b){		
			$t_a = strtotime($a['created_at']);
			$t_b = strtotime($b['created_at']);
			
			return $t_a - $t_b;
		});
		
		header("Content-Type: text/xml");
		echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
?>
<rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" version="2.0">
	<channel>
		<title><?php $this->h($feed['title']); ?></title>
		<link><?php $this->h($feed['link']); ?></link>
		<language><?php $this->h($feed['lang']); ?></language>
		<copyright><?php $this->h($feed['copyright']); ?></copyright>
		<itunes:author><?php $this->h($feed['author']); ?></itunes:author>
		<description><?php $this->h($feed['description'].' このコンテンツは、'.$user_email.'に提供しています。'); ?></description>
		<itunes:category text="<?php $this->h($feed['category']);?>"/>
		<itunes:image href="<?php $this->h($feed['image']) ?>"/>
		<lastBuildDate><?php $this->h($feed['pubDate']); ?></lastBuildDate>
		<itunes:keywords></itunes:keywords>
<?php
        
		$cnt = 0;
		$item_date = $feed['date'];
		
		foreach($tracks as $track){
			$cnt ++;
			
			//permalink から、secret key を削除
			$tmp_arr = array();
			if(	preg_match('/(.*)\/'.$sec_key.'$/', $track['permalink_url'], $tmp_arr) )
			{
				$permalink = $tmp_arr[1];
			}
			else{
				$permalink = $track['permalink_url'];
			}
			
			
			$i_type = 'audio/mpeg';
			
			$sec = $track['duration'] / 1000;
			$h = floor($sec / (60*60) );
			$m = floor(($sec/60) % 60);
			$s = $sec % 60;
			
			        	
			$i_title = $track['title'];
			$i_link = $permalink; //TODO : secret_key を削除する
			$i_author = $track['user']['username'];
			$i_category = $track['genre'];
			$i_subcategory ='';
			$i_order = $cnt;
			$i_duration = sprintf("%02d:%02d:%02d",$h, $m, $s);
			$i_description = $track['description'];
			$i_pubdate = date("D, d M Y H:i:s O", strtotime($track['created_at']));
			$i_enc_url = $this->file_url_path.$track['id'].'.mp3';
			
			$fpath = $this->path.$track['id'].'.mp3';
			$i_length = file_exists($fpath) ? filesize($fpath) : 0;
			
			$i_guid = 'tag:soundcloud,2010:tracks/'.$track['id'];
			$i_cnt = isset($track['episode_num']) ? $track['episode_num'] : $cnt;
			
			//未来の日付だったら、表示しない
			if( strtotime($i_pubdate) > time() )
			{
				continue;
			}
	
?>
		<item>
			<title><?php $this->h($i_title);?></title>
			<enclosure type="<?php $this->h($i_type);?>" url="<?php $this->h($i_enc_url);?>" length="<?php $this->h($i_length);?>"></enclosure>
			<pubDate><?php $this->h($i_pubdate);?></pubDate>			
			<guid isPermaLink="false"><?php $this->h($i_guid);?></guid>
			<description><?php $this->h($i_description.' for '.$user_email);?></description>
			<itunes:author><?php $this->h($i_author);?></itunes:author>
			<itunes:duration><?php $this->h($i_duration);?></itunes:duration>
			<itunes:episode><?php $this->h($i_cnt); ?></itunes:episode>
		</item>
<?php
		}
?>
    </channel>
</rss>
<?php
	}
	
	function h($str){
		echo htmlspecialchars($str, ENT_QUOTES);
	}
	
}
