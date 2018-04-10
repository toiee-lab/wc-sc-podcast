# 概要

WooCommerce のプロダクトID（メンバー、サブスクリプションにも対応）を使って、ユーザー固有のPodcast URLを生成し、提供するためのプラグイン。オーディオのソースは、SoundCloudのプレイリストを利用する。

SoundCloudを利用することで、アップロード作業は簡単になる上、Webページ内に埋め込むのも容易になるし、オーディオの管理もしやすくなる。


## インストール方法

1. プラグインをインストール
2. SoundCloudでアプリキーを取得
3. 設定 -> SC Podcast の設定で アプリキーを設定

## 使い方

### 以下のような準備が必要

1. SoundCloudでプレイリストを作る
2. プレイリストの「embed」タグを取得する
3.  SC Podcast で新規追加
4.  Embed code の欄に、2のコードを設定する
5.  その他、制限などを設定する

### ユーザー固有のPodcastを貼り付ける方法

SC Podcast一覧で、「shortcode」が取得できるので、それを使う。

```
[scpodcast id="XXX" type="url"]    // urlをコピーできるテキストボックス
[scpodcast id="XXX" type="link"]   // リンク
[scpodcast id="XXX"]   // embed code
```

なお、embed code については、アクセス制限など、何も考えてないので使わないように（笑）。そのうちなんとかします。

ステップは以下の通り。

1. 貼り付けたいページを開く
2. 上記のようなショートコードを貼り付ける
3. ユーザーがログインしてアクセスする
4. それを Podcast に登録すれば、利用できる




## 仕様（メモ）

### Podcastの仕様と、SoundCloudの仕様

Podcast の item に使う url (enclosure の url）は、リダイレクトが設定されているとエラーとなって、Podcast 自体を読み込まない（ iOSの場合）。おそらくは、セキュリティ対策なのだろう。

SoundCloud のオーディオのURLは、固定的に決まっているように見えるが、実際にダウンロードを行う際は、 **必ずリダイレクト** される。しかも、 **毎回、違うURLに** 転送される。これは、不正なダウンロードを防ぐための仕組み（音楽を盗む人への対策）と思われる。

SoundCloud のオーディオURLは、転送されてしまうので、Podcastの enclosure の url としては使えない。

> このような背景もあって、SoundCloud は、Podcast専用の機能を別で実装。その中で使われているオーディオのURLは転送されない

以上のことから、このプラグインでは、

- SoundCloud から、オーディオデータをダウンロードして
- WordPress内に保存して、そこからPodcastとして配信している

したがって、Podcastへのアクセスは、WordPressが設置されているサーバーに負荷をかけることになるが、Podcastアプリがキャッシュしてくれるし、おそらくは同時ダウンロードも少ないので、大丈夫だろう。

> メモです

### プレイリストと情報取得

SoundCloud のプレイリストやトラックは「プライベートモード」が可能。一覧には出なくなる。この機能を使った場合、プレイリストやオーディオにアクセスするには、

- プレイリストID
- プレイリスト別のシークレットトークン
- アプリ認証トークン

が必要。

プレイリストIDと、プレイリスト別のシークレットトークンは、Playlist の embed タグで取得できる。

そこで、このプラグインでは「プレイリストのembedタグ」を登録するようになっている。


### WooCommerce連携

WooCommerceを使うことによって、幅広く、様々な「限定Podcast」を生成して、配信することができるようになる。たとえば、メンバー限定、この商品を買った人限定、サブスクリプション限定など。


### カスタム投稿タイプ

- scpcast という投稿タイプを設定
- slug は、 /scpcast/?token=XXXX 



