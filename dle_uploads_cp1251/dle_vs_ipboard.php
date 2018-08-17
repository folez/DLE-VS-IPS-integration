<?php
if (!function_exists('clean_url'))
{
	function clean_url($url)
	{

		if ($url == '') return;

		$url = str_replace("http://", "", $url);
		if (strtolower(substr($url, 0, 4)) == 'www.')  $url = substr($url, 4);
		$url = explode('/', $url);
		$url = reset($url);
		$url = explode(':', $url);
		$url = reset($url);

		return $url;
	}
}

if (!function_exists('legacyEscape')){
	function legacyEscape( $val )
		{
			$val = str_replace( "&"			, "&amp;"         , $val );
			$val = str_replace( "<!--"		, "&#60;&#33;--"  , $val );
			$val = str_replace( "-->"		, "--&#62;"       , $val );
			$val = str_ireplace( "<script"	, "&#60;script"   , $val );
			$val = str_replace( ">"			, "&gt;"          , $val );
			$val = str_replace( "<"			, "&lt;"          , $val );
			$val = str_replace( '"'			, "&quot;"        , $val );
			$val = str_replace( "\n"		, "<br />"        , $val );
			$val = str_replace( "$"			, "&#036;"        , $val );
			$val = str_replace( "!"			, "&#33;"         , $val );
			$val = str_replace( "'"			, "&#39;"         , $val );
			$val = str_replace( "\\"		, "&#092;"        , $val );
			
			return $val;
		}
}
if (!function_exists('encryptedPassword')){
	function encryptedPassword( $password, $salt )
	{
		/* New password style introduced in IPS4 using Blowfish */
		if ( mb_strlen( $salt ) === 22 )
		{
			return crypt( $password, '$2a$13$' . $salt );
		}
		/* Old encryption style using md5 */
		else
		{
			return md5( md5( $salt ) . legacyEscape( $password ) );
		}
	}
}

if (!function_exists('generateSalt')){
	function generateSalt()
	{
		$salt = '';
		for ( $i=0; $i<22; $i++ )
		{
			do
			{
				$chr = rand( 48, 122 );
			}
			while ( in_array( $chr, range( 58,  64 ) ) or in_array( $chr, range( 91,  96 ) ) );

			$salt .= chr( $chr );
		}
		return $salt;
	}
}

class ipb_member
{
	public $member = array();
	protected $connect_method = 'connect';
	protected $config = array();
	public $lang = array();
	public $ipb_config = array();
	protected $db = null;
	protected $connected = false;

	function __construct(db &$db)
	{
		$lang_dle_ipb = array();
		require_once(ROOT_DIR.'/language/Russian/dle_ipb.lng');
		$this->lang = $lang_dle_ipb;
		$key = file_get_contents( ROOT_DIR."/{$_SERVER['HTTP_HOST']}.lic" );
		$check_license = file_get_contents("http://dle-integration.ru/api.php?domain={$_SERVER['HTTP_HOST']}&key={$key}");
		$check_license = json_decode($check_license);
		if ( $check_license->status == "expire" ) {
			echo $this->lang['expire'];exit;
		} elseif ( $check_license->banned == 1 ) {
			echo $this->lang['banned'];exit;
		} elseif ( $check_license->status == 'error_key' ) {
			echo $this->lang['error_key'];exit();
		} elseif ( $check_license->status == 'error_domain' ) {
			echo $this->lang['error_domain'];exit();
		}

		if (file_exists(ENGINE_DIR . "/data/dle_ipb_conf.php")) {
			$dle_ipb_conf = array();
			include(ENGINE_DIR . "/data/dle_ipb_conf.php");
			$this->config = $dle_ipb_conf;
		} else {
			die("Модуль интеграции не установлен");
		}

		if (file_exists(ROOT_DIR . "/conf_global.php")) {
			$INFO = array();
			include(ROOT_DIR . "/conf_global.php");
			$this->ipb_config = $INFO;
		} else {
			die("Не найден конфиг форума conf_global.php");
		}

		if (!defined('IPB_CHARSET')) {
			define('IPB_CHARSET', 'UTF-8');
		}

		if ($GLOBALS['config']['charset']) {
			define('DLE_CHARSET', $GLOBALS['config']['charset']);
		} else {
			define('DLE_CHARSET', 'windows-1251');
		}
		if (!defined('COLLATE')) {
			define('COLLATE', 'cp1251');
		}
		$this->db =& $db;
		define('IPB_PREFIX', $this->IPBConfig('sql_tbl_prefix'));
		if ($this->ipb_config['sql_host'] === DBHOST && 
			$this->ipb_config['sql_user'] === DBUSER &&
			$this->ipb_config['sql_pass'] === DBPASS
			) {
			if ($this->ipb_config['sql_database'] === DBNAME) {
				$this->connect_method = 'none';
			} else {
				$this->connect_method = 'use';
			}
		}
		if (isset($_REQUEST['do']) && 
			$_REQUEST['do'] == "goforum" && 
			$this->config['goforum'] && 
			!empty($_REQUEST['postid']) && 
			$this->config['allow_module']
			)
		{
			$this->GoForum();
		}
		require_once($this->config['path_init']);
		\IPS\Session\Front::i();
	}
	
	public function &_db_connect()
	{
		if ($this->connected) { return $this->db; }
		switch ($this->connect_method) {
			case "none":
			break;
			case "use":
			$this->db->query("USE `" . $this->ipb_config['sql_database'] . "`");
			break;
			default:
			$this->db->connect(
				$this->ipb_config['sql_user'], 
				$this->ipb_config['sql_pass'], 
				$this->ipb_config['sql_database'], 
				$this->ipb_config['sql_host']
				);
			break;
		}
		if ($this->ipb_config['sql_charset'] && $this->ipb_config['sql_charset'] != COLLATE) {
			$this->db->query("SET NAMES '{$this->ipb_config['sql_charset']}'");
		}
		if (isset($this->ipb_config['sql_character'])) {
			if ($this->ipb_config['sql_character']) {
				$this->db->query("SET CHARACTER SET '{$this->ipb_config['sql_character']}'");
			}
		} else if ($this->ipb_config['sql_charset']) {
			$this->db->query("SET CHARACTER SET '{$this->ipb_config['sql_charset']}'");
		}
		$this->connected = true;
		return $this->db;
	}

	public function _db_disconnect()
	{
		if ($this->connected)
		{
			switch ($this->connect_method) {
				case "none":
				break;
				case "use":
				$this->db->query("USE `" . DBNAME . "`");
				break;
				default:
				$this->db->connect(DBUSER, DBPASS, DBNAME, DBHOST);
				break;
			}
			if ($this->ipb_config['sql_charset'] && $this->ipb_config['sql_charset'] != COLLATE) {
				$this->db->query("SET NAMES '" . COLLATE . "'");
			}
			$this->connected = false;
		}
	}

	public function _convert_charset(&$text, $back = false)
	{
		if (IPB_CHARSET && IPB_CHARSET != DLE_CHARSET)
		{
			if (!$back) {
				$text = iconv(DLE_CHARSET, IPB_CHARSET, $text);
			} else {
				$text = iconv(IPB_CHARSET, DLE_CHARSET, $text);
			}
		}
		return $text;
	}

	public function IPBConfig($varname)
	{
		if (isset($this->ipb_config[$varname])) {
			return $this->ipb_config[$varname];
		}
		/*if (!function_exists("dle_cache") || !($cache = dle_cache("config_ipb"))) {
			$this->db->query("SELECT * FROM " . IPB_PREFIX . "core_sys_conf_settings");
			while ($row = $this->db->get_row()) {
				$this->ipb_config[$row['conf_key']] = ($row['conf_value'] != "")?$row['conf_value']:$row['conf_default'];
			}
			if (function_exists('create_cache')) {
				create_cache("config_ipb", serialize($this->ipb_config));
			}
		} else {
			$this->ipb_config += unserialize($cache);
		}*/
		if (isset($this->ipb_config[$varname])) {
			return $this->ipb_config[$varname];
		} else {
			return '';
		}
	}

	protected function ip()
	{
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			return $_SERVER['REMOTE_ADDR'];
		} else {
			return 'not detected';
		}
	}

	public function generateSalt()
	{
		$salt = '';
		for ( $i=0; $i<22; $i++ ) {
			do
			{
				$chr = rand( 48, 122 );
			}
			while ( in_array( $chr, range( 58,  64 ) ) or in_array( $chr, range( 91,  96 ) ) );
			$salt .= chr( $chr );
		}
		return $salt;
	}

	protected function _findForumUser($username, $email = '')
	{
		$this->_convert_charset($username);
		$username_sql = $this->db->safesql($username);

		if ($email) {
			$email = $this->db->safesql($email);
			$this->_convert_charset($email);

			$res = $this->db->query("SELECT * FROM " . IPB_PREFIX . "core_members WHERE name='$username_sql' OR email='$email'");
			if ($this->db->num_rows($res) > 1) {
				while($user = $this->db->get_row($res))
				{
					if ((!defined('CHECK_LOGIN_BY_EMAIL') || !CHECK_LOGIN_BY_EMAIL) && $user['name'] == $username) {
						$this->member = $user;
						break;
					} else if ($user['email'] == $email) {
						$this->member = $user;
						break;
					}
				}
			}
			else
			{
				$this->member = $this->db->get_row($res);
			}
		}
		else
		{
			$this->member = $this->db->super_query("SELECT * FROM " . IPB_PREFIX . "core_members WHERE name='$username_sql' LIMIT 1");
		}
	}

	public function getForumPath()
	{
		$path = mb_substr( $this->ipbConfig('base_url'), mb_strpos( $this->ipbConfig('base_url'), ( !empty( $_SERVER['SERVER_NAME'] ) ) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'] ) + mb_strlen( ( !empty( $_SERVER['SERVER_NAME'] ) ) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'] ) );
		$path = mb_substr( $path, mb_strpos( $path, '/' ) );
		return $path;
	}

	public function GetForumDomain()
	{
		$domain = clean_url($this->IPBConfig('base_url'));
		return $domain;
	}

	public function login(array $member)
	{
		if (!$this->config['allow_module'] || !$this->config['allow_login']) {
			return true;
		}

		if (isset($_REQUEST['action']) && $_REQUEST['action'] == "logout") {
			return true;
		}

		$domain = $this->GetForumDomain();
		$path = $this->getForumPath();
		$this->_db_connect();
		$TIME = time();

		if (!empty($_COOKIE["session_id"]))
		{
			$session_id = $this->db->safesql($_COOKIE["session_id"]);
		}
		else if (!empty($_COOKIE['forum_session_id']))
		{
			$session_id = $this->db->safesql($_COOKIE['forum_session_id']);
		}
		else
		{
			$session_id = '';
		}

		if (!empty($member['name']))
		{
			$name = $this->db->safesql($member['name']);
			$this->_convert_charset($name);
		}
		if (
			!$session_id || ($row['member_id'] == 0 && !empty($member['user_id'])) ||
			(empty($member['name']) && !empty($_POST['login_name'])) ||
			(!empty($name) && $row['name'] != $name)
			)
		{
			if (empty($this->member) && !empty($member['name']))
			{
				$this->_findForumUser($member['name'], $member['email']);
			}
			if (!empty($_POST['login_password']))
			{
				$pass = $this->db->safesql($_POST['login_password']);
			}
			else if (!empty($_SESSION['dle_password']))
			{
				$pass = $this->db->safesql($_SESSION['dle_password']);
			}
			else if (!empty($_COOKIE['dle_password']))
			{
				$pass = $this->db->safesql($_COOKIE['dle_password']);
			}

			if (empty($this->member['member_id']) AND $member['user_id'] > 0)
			{
				$this->CreateMember($this->db->safesql($member['name']), $member['password'], $member['pass_salt'], $this->db->safesql($member['email']), $TIME);
			} else if (!defined('CHECK_LOGIN_PASS') || CHECK_LOGIN_PASS) {
				if( is_md5hash( $pass ) ) {
					if (md5($this->member['members_pass_hash']) !== $pass)
					{
						$this->member = array();
						$this->member['member_id'] = 0;
					}
				} else {
					if ($this->member['members_pass_hash'] !== $pass)
					{
						$this->member = array();
						$this->member['member_id'] = 0;
					}
				}
			} else if (empty($member['name']) && !empty($_POST['login_name'])) {
				$name = $this->db->safesql($_POST['login_name']);
				$this->_convert_charset($name);
				$password = $_POST['login_password'];
				$this->_convert_charset($password);
				$this->member = $this->db->super_query("SELECT * FROM " . IPB_PREFIX . "core_members WHERE name='{$name}' LIMIT 1");
				$members_pass_salt = $this->member['members_pass_salt'];
				$pass = encryptedPassword( $password, $members_pass_salt );
				if ($this->member && $this->member['members_pass_hash'] == $pass) {
					$this->_db_disconnect();
					$member = $this->_create_dle_account();
					$this->_db_connect();
				} else {
					$this->member = array();
					$name = '';
				}
			}
			if (empty($this->member['member_id']) || empty($member['user_id']))
			{
				$this->member = array();

				$this->member['member_id'] = 0;
				$this->member['name'] = "";
				$this->member['members_display_name'] = "";
				$this->member['members_seo_name'] = "";
				$this->member['member_group_id'] = 2;
			}
			if (!empty($this->member['member_id']))
			{
				$_member = \IPS\Member::load( $this->member['name'], 'name' );

				if ( $_member->member_id > 0)
				{
					$i_member = $_member;
				}

				$device = \IPS\Member\Device::loadOrCreate( $i_member );
				\IPS\Session::i()->setMember( $i_member );
				$login_key = $this->generateRandomString();
				$cookieExpiration = ( new \IPS\DateTime )->add( new \DateInterval( "P3M" ) );
				\IPS\Request::i()->setCookie( 'member_id', $i_member->member_id, $cookieExpiration );
				\IPS\Request::i()->setCookie( 'login_key', $login_key, $cookieExpiration );

				$this->db->query("UPDATE " . IPB_PREFIX . "core_members SET last_visit='".time()."', ip_address='".$this->db->safesql($this->ip())."' WHERE member_id='".$i_member->member_id."'");
			}
		} elseif (!empty($row['id'])) {
			if (!empty($row['member_id']))
			{
				$this->member = $row;
			}
		}

		$this->_db_disconnect();
	}

	public function logout()
	{
		if (!$this->config['allow_module'] || !$this->config['allow_logout'])
		{
			return true;
		}
		if (!empty($_SESSION['dle_name'])) 
			$name = $_SESSION['dle_name'];
		elseif (!empty($_COOKIE['dle_name']))
			$name = $_COOKIE['dle_name'];
		elseif (!empty($_SESSION['dle_user_id']) || !empty($_COOKIE['dle_user_id'])) {
			$id = (empty($_SESSION['dle_user_id']))?intval($_COOKIE['dle_user_id']):intval($_SESSION['dle_user_id']);
			$name = $this->db->super_query("SELECT name FROM " . USERPREFIX . "_users WHERE user_id='$id'");
			$name = $name['name'];
		}

		if (!$name) {
			return false;
		}

		$this->_db_connect();

		if (empty($this->member['member_id'])) {
			$this->_convert_charset($name);

			$this->member = $this->db->super_query("SELECT * FROM " . IPB_PREFIX . "core_members WHERE name='$name' LIMIT 1");
		}

		if (!empty($this->member['member_id'])) {
			$this->db->query("UPDATE " . IPB_PREFIX . "core_members SET last_visit='" . time() . "', last_activity='" . time() . "' WHERE member_id=" . $this->member['member_id']);
		}

		$domain = $this->GetForumDomain();
		$path = $this->getForumPath();
		if( isset( $_SESSION['logged_in_as_key'] ) )
		{
			$key = $_SESSION['logged_in_as_key'];
			unset( \IPS\Data\Store::i()->$key );
			unset( $_SESSION['logged_in_as_key'] );
			unset( $_SESSION['logged_in_from'] );
		}
		\IPS\Request::i()->setCookie( 'member_id', NULL );
		\IPS\Request::i()->setCookie( 'login_key', NULL );

		$this->_db_disconnect();

		return true;
	}

	public static function generateRandomString( $length=32 )
	{
		$return = '';

		if( function_exists( 'openssl_random_pseudo_bytes' ) )
		{
			$return = \substr( bin2hex( openssl_random_pseudo_bytes( ceil( $length / 2 ) ) ), 0, $length );
		}

		/* Fallback JUST IN CASE */
		if( !$return OR \strlen( $return ) != $length )
		{
			$return = \substr( md5( uniqid( microtime(), true ) ) . md5( uniqid( microtime(), true ) ), 0, $length );
		}

		return $return;
	}

	/**
	 * Generate block with last post in forum
	 *
	 * @param dle_template $tpl
	 * @return string
	 */
	public function last_forum_posts(dle_template &$tpl)
	{
		if (!$this->config['allow_module'] || !$this->config['allow_forum_block'])
		{
			return '';
		}

		if ((int)$this->config['block_new_cache_time'] && 
			file_exists(ENGINE_DIR . "/cache/last_forum_posts.tmp") &&
			(time() - filemtime(ENGINE_DIR . "/cache/last_forum_posts.tmp")) > (int)$this->config['block_new_cache_time'] &&
			$cache = dle_cache("last_forum_posts")
			)
		{
			$tpl->result['last_forum_posts'] = $cache;
		}

		$this->_db_connect();

		if ($this->config['bad_forum_for_block'] != "")
		{
			$forum_bad = explode(",", $this->config['bad_forum_for_block']);
			$forum_id = " WHERE forum_id NOT IN('". implode("','", $forum_bad) ."')";
		}
		elseif ($this->config['good_forum_for_block'] != "")
		{	
			$forum_good = explode(",", $this->config['good_forum_for_block']);
			$forum_id = " WHERE forum_id IN('". implode("','", $forum_good) ."')";
		}
		else
		{
			$forum_id = "";
		}

		if ($forum_id !="")
		{
			$forum_id .= " AND state='open' AND approved=1";
		}
		else 
		{
			$forum_id .= " WHERE state='open' AND approved=1";
		}

		if (!(int)$this->config['count_post'])
		{
			die("Не указано количество постов для блока сообщений с форума");
		}

		$result = $this->db->query("SELECT t.posts, 
			t.views, 
			t.forum_id, 
			t.tid, 
			t.topic_firstpost, 
			t.title, 
			t.title_seo, 
			t.last_post, 
			t.last_poster_name, 
			t.last_poster_id, 
			t.starter_name, 
			t.starter_id,  
			f.seo_last_title, 
			f.seo_last_name, 
			f.name_seo AS fname_seo,
			p.pid,
			p.post AS p_content 
			FROM " . IPB_PREFIX . "forums_topics AS t
			LEFT JOIN " . IPB_PREFIX . "forums_forums AS f
			ON f.id=t.forum_id
			LEFT JOIN ".IPB_PREFIX."forums_posts as p
			ON p.pid=t.topic_firstpost
			". $forum_id ." ORDER BY t.last_post DESC LIMIT 0 ," . (int)$this->config['count_post']);

		$tpl->load_template('dle_ipb/block_forum_posts.tpl');
		preg_match("'\[row\](.*?)\[/row\]'si", $tpl->copy_template, $matches);

		while ($row = $this->db->get_row($result))
		{
			foreach ($row as &$value)
			{
				$this->_convert_charset($value, true);
			}
			$lang_row = $this->db->super_query("SELECT * FROM core_sys_lang_words WHERE word_app='forums' AND word_key='forums_forum_".$row['forum_id']."' ");
			$short_name=$name=$row["title"];
			quoted_printable_decode($name);

			if ($this->config['leght_name'])
			{
				if (strlen($name) > $this->config['leght_name'])
				{
					if (function_exists('mb_substr'))
					{
						$short_name = mb_substr ($name, 0, $this->config['leght_name'], DLE_CHARSET)." ..."; 	
					}
					else 
					{
						$short_name = substr ($name, 0, $this->config['leght_name'])." ...";
					}
				}
			}

			if (isset($this->member['time_offset']) && (int)$this->member['time_offset'] && !((int)$this->config['block_new_cache_time']))
			{
				$row["last_post"] += ($this->member['time_offset'] - $this->IPBConfig('time_offset'))*3600;
			}

            switch (date("d.m.Y", $row["last_post"]))
            {
            	case date("d.m.Y"):
            	$date=date($this->lang['today_in'] . "H:i", $row["last_post"]);	
            	break;

            	case date("d.m.Y", time()-86400):
            	$date=date($this->lang['yestoday_in'] . "H:i", $row["last_post"]);	
            	break;

            	default:
            	$date=date("d.m.Y H:i", $row["last_post"]);
            }
            $content_post = dle_substr( $row['p_content'], 0, $this->config['leght_name'], $config['charset']);
            $replace = array('{user}'=> $row['last_poster_name'],
            	'{user_url}' => ($this->config['forum_block_alt_url'])?
            	$this->ipb_config['base_url'] . "index.php?/profile/{$row['last_poster_id']}-{$row['seo_last_name']}/":
            	$this->ipb_config['base_url'] . "index.php?app=core&module=members&controller=profile&id=".$row['last_poster_id']
            	,
            	'{author_url}' => ($this->config['forum_block_alt_url'])?
            	$this->ipb_config['base_url']."index.php?/profile/{$row['starter_id']}-{$row['seo_first_name']}/":
            	$this->ipb_config['base_url']."index.php?app=core&module=members&controller=profile&id=".$row['starter_id'],
            	'{reply_count}'=> $row["posts"],
            	'{view_count}'=> $row["views"],
            	'{full_name}'=> $name,
            	'{author}'=> $row['starter_name'],
            	'{forum}'=> $lang_row['word_custom'],
            	'{forum_url}'=> ($this->config['forum_block_alt_url'])?
            	$this->ipb_config['base_url']."index.php?/forum/{$row['forum_id']}-{$row['fname_seo']}/":
            	$this->ipb_config['base_url']."index.php?app=forums&module=forums&controller=forums&id=".$row['forum_id'],
            	'{post_url}'=>  ($this->config['forum_block_alt_url'])?
            	$this->ipb_config['base_url']."index.php?/topic/{$row['tid']}-{$row['title_seo']}":
            	$this->ipb_config['base_url']."index.php?app=forums&module=forums&controller=topic&id=".$row["tid"]."&amp;view=getnewpost",
            	'{shot_name_post}'=> $short_name,
            	'{p_content}'=> $content_post,
            	'{date}'=> $date,);

            $tpl->copy_template = strtr($tpl->copy_template, $replace);
            $tpl->copy_template = preg_replace("'\[row\](.*?)\[/row\]'si", "\\1\n".$matches[0], $tpl->copy_template);
        }
        $tpl->set_block("'\[row\](.*?)\[/row\]'si", "");
        $tpl->compile('block_forum_posts');
        $tpl->clear();
        $this->db->free();

        $this->_db_disconnect();

        if ((int)$this->config['block_new_cache_time'])
        {
        	create_cache("last_forum_posts", $tpl->result['block_forum_posts']);
        }
    }

	/**
	 * Create account in forum
	 *
	 * @param string $user_name
	 * @param string $password Password
	 * @param string $salt Salt
	 * @param string $email
	 * @param int $add_time
	 * @return boolean
	 */
	public function CreateMember($user_name, $password, $salt, $email, $add_time = '')
	{
		if (!$this->config['allow_module'] || !$this->config['allow_reg'])
		{
			return true;
		}

		$user_l_name = strtolower($user_name);

		$this->_convert_charset($user_l_name);
		$this->_convert_charset($user_name);
		$this->_convert_charset($password);

		$no_connect = true;

		if (!$this->connected)
		{
			$this->_db_connect();
			$no_connect = false;
		}

		if ($this->db->super_query("SELECT member_id FROM " . IPB_PREFIX . "core_members WHERE name='$user_name'"))
		{
			if (!$no_connect)
			{
				$this->_db_disconnect();
			}
			return true;
		}
		if (function_exists('dle_cache'))
		{
			$lang = dle_cache('default_language');
		}
		else
		{
			$lang = false;
		}

		if (!$add_time)
		{
			$add_time = time() + ($GLOBALS['config']['date_adjust'] * 60);
		}
		$data['name']     = $user_name;
		$data['email']      = $email;
		$data['members_pass_hash']  = $password;
		$data['members_pass_salt']  = $salt;
		$data['allow_admin_mails']  = 1;
		$data['language']  = $lang;
		$data['member_group_id']    = $this->IPBConfig('member_group');
		$data['members_bitoptions']['view_sigs'] = TRUE;
		$data['ip_address']				= $this->db->safesql($this->ip());
		if ( $this->config['ipb_version'] == 0 ) {
			$data['member_login_key']		= $this->generateRandomString();
			$data['member_login_key_expire'] = strtotime("now +3 month");
		}
		$data['members_seo_name'] = totranslit( $user_name, true, false );
		$data['joined']				= $add_time;

		$this->db->query("INSERT INTO " . IPB_PREFIX . "core_members (" . implode(",", array_keys($data)) . ") VALUES ('" . implode("', '", $data) . "')");
		$id = $this->db->insert_id();

		if (!$no_connect)
		{
			$this->_db_disconnect();
		}

		$this->member = $data;
		$this->member['member_id'] = $id;

		return true;
	}

	public function UpdateProfile($user_name, $email, $password, $info, $admin = false)
	{
		if (!$this->config['allow_module'] || !$this->config['allow_profile'])
		{
			return true;
		}

		if ($admin)
		{
			$user_dle = $this->db->super_query("SELECT * FROM " . USERPREFIX . "_users WHERE user_id=" . $user_name);

			if (empty($user_dle['name']))
			{
				return false;
			}
			else
			{
				$user_name = $this->db->safesql($user_dle['name']);
			}
		}

		$this->_convert_charset($user_name);
		$this->_convert_charset($land);
		$this->_convert_charset($icq);
		$this->_convert_charset($info);

		$this->_db_connect();

		if (empty($this->member['member_id']))
		{
			$this->member = $this->db->super_query("SELECT * FROM " . IPB_PREFIX . "core_members WHERE name='$user_name' LIMIT 1");
		}

		if (empty($this->member['member_id']))
		{
			$this->_db_disconnect();
			return false;
		}

		if (strlen($password) > 0)
		{
			$salt = $this->member['members_pass_salt'];
			$this->_convert_charset($password);
			$this->_convert_charset($salt);

			if ($admin || $GLOBALS['config']['version_id'] > 8.2)
			{
				$password           = html_entity_decode($password, ENT_QUOTES);
				$html_entities      = array( "&#33;", "&#036;", "&#092;" );
				$replacement_char   = array( "!", "$", "\\" );
				$password           = str_replace( $html_entities, $replacement_char, $password );
			}

			$passhash = crypt($password, '$2a$13$' . $salt);
			$change_pass = ", members_pass_hash='$passhash'";
		} 
		else
		{
			$change_pass = '';
		}
		$this->db->query("UPDATE " . IPB_PREFIX . "core_members SET email='$email'$change_pass WHERE member_id=" . $this->member['member_id']); 

		$this->_db_disconnect();

		return true;
	}

	public function LostPassword($user_name, $new_pass, $new_salt)
	{
		if (!$this->config['allow_module'] || !$this->config['allow_lostpass'])
		{
			return true;
		}
		$this->_db_connect();
		$this->_convert_charset($user_name);
		$this->_convert_charset($new_pass);
		$test = "UPDATE " . IPB_PREFIX . "core_members SET members_pass_hash='$new_pass', members_pass_salt='$new_salt' WHERE name='$user_name'";
		$this->db->query($test);
		$this->_db_disconnect();
	}

	public function DeleteUser($user_name)
	{
		if (!$this->config['allow_module'] || !$this->config['allow_admin'])
		{
			return true;
		}

		$this->_convert_charset($user_name);

		$user_name = $this->_db_connect()->safesql($user_name);

		$member = $this->db->super_query("SELECT * FROM " . IPB_PREFIX . "core_members WHERE name='$user_name' LIMIT 1");

		if (empty($member['member_id']))
		{
			$this->_db_disconnect();
			return false;
		}

		$this->db->query("UPDATE " . IPB_PREFIX . "forums_posts SET author_id=0 WHERE author_id=" . $member['member_id']);
		$this->db->query("UPDATE " . IPB_PREFIX . "forums_topics SET starter_id=0 WHERE starter_id=" . $member['member_id']);
		$this->db->query("UPDATE " . IPB_PREFIX . "core_announcements SET announce_member_id=0 WHERE announce_member_id=" . $member['member_id']);
		$this->db->query("UPDATE " . IPB_PREFIX . "core_attachments SET attach_member_id=0 WHERE attach_member_id=" . $member['member_id']);
		$this->db->query("UPDATE " . IPB_PREFIX . "core_polls SET starter_id=0 WHERE starter_id=" . $member['member_id']);
		$this->db->query("UPDATE " . IPB_PREFIX . "core_voters SET member_id=0 WHERE member_id=" . $member['member_id']);
		$this->db->query("UPDATE " . IPB_PREFIX . "forums_forums SET last_poster_name='', seo_last_name='' WHERE last_poster_id=" . $member['member_id']);
		$m_check = $this->db->super_query("SELECT * FROM " . IPB_PREFIX . "core_moderators WHERE type='m' AND id=" . $member['member_id']." LIMIT 1");
		if (!empty($m_check['member_id'])) {
			$this->db->query("DELETE FROM " . IPB_PREFIX . "core_moderators WHERE type='m' AND id=" . $member['member_id']);
		}
		$w_check = $this->db->super_query("SELECT * FROM " . IPB_PREFIX . "core_members_warn_logs WHERE wl_member=" . $member['member_id']." LIMIT 1");
		if (!empty($w_check['member_id'])) {
			$this->db->query("DELETE FROM " . IPB_PREFIX . "core_members_warn_logs WHERE wl_member=" . $member['member_id']);
		}
		$this->db->query("DELETE FROM " . IPB_PREFIX . "core_sessions WHERE member_id=" . $member['member_id']);
		$this->db->query("UPDATE " . IPB_PREFIX . "core_members_warn_logs SET wl_moderator=0 WHERE wl_moderator=" . $member['member_id']);
		$adm_check = $this->db->super_query("SELECT * FROM " . IPB_PREFIX . "core_admin_permission_rows WHERE row_id_type='member' AND row_id=" . $member['member_id']." LIMIT 1");
		if (!empty($adm_check['member_id'])) {
			$this->db->query("DELETE FROM " . IPB_PREFIX . "core_admin_permission_rows WHERE row_id_type='member' AND row_id=" . $member['member_id']);
			$this->db->query("DELETE FROM " . IPB_PREFIX . "core_sys_cp_sessions WHERE session_member_id=" . $member['member_id']);
		}

		$this->db->query("DELETE FROM " . IPB_PREFIX . "core_message_topic_user_map WHERE map_user_id=" . $member['member_id']);
		$this->db->query("UPDATE " . IPB_PREFIX . "core_message_posts SET msg_author_id=0 WHERE msg_author_id=" . $member['member_id']);
		$this->db->query("UPDATE " . IPB_PREFIX . "core_message_topics SET mt_starter_id=0 WHERE mt_starter_id=" . $member['member_id']);
		$this->db->query("DELETE FROM " . IPB_PREFIX . "core_ignored_users WHERE ignore_owner_id=" . $member['member_id'] . " or ignore_ignore_id=" . $member['member_id']);
		$this->db->query("DELETE FROM " . IPB_PREFIX . "core_item_markers WHERE item_member_id=" . $member['member_id']);
		$this->db->query("DELETE FROM " . IPB_PREFIX . "core_validating WHERE member_id=" . $member['member_id']);
		$this->db->query("DELETE FROM " . IPB_PREFIX . "core_members WHERE member_id=" . $member['member_id']);

		$this->_db_disconnect();
	}

	/**
	* @block Block online
	*/
	protected function browser($useragent)
	{
		$browser_type = "Unknown";
		$browser_version = "";
		if (@preg_match('#MSIE ([0-9].[0-9]{1,2})#', $useragent, $version)) {
			$browser_type = "Internet Explorer";
			$browser_version = $version[1];
		} elseif (@preg_match('#Opera ([0-9].[0-9]{1,2})#', $useragent, $version)) {
			$browser_type = "Opera";
			$browser_version = $version[1];
		} elseif (@preg_match('/Opera/i', $useragent)) {
			$browser_type = "Opera";
			$val = stristr($useragent, "opera");
			if (preg_match("#/#", $val)){
				$val = explode("/",$val);
				$browser_type = $val[0];
				$val = explode(" ",$val[1]);
				$browser_version  = $val[0];
			} else {
				$val = explode(" ",stristr($val,"opera"));
				$browser_type = $val[0];
				$browser_version  = $val[1];
			}
		} elseif (@preg_match('/Firefox\/(.*)/i', $useragent, $version)) {
			$browser_type = "Firefox";
			$browser_version = $version[1];
		} elseif (@preg_match('/SeaMonkey\/(.*)/i', $useragent, $version)) {
			$browser_type = "SeaMonkey";
			$browser_version = $version[1];
		} elseif (@preg_match('/Minimo\/(.*)/i', $useragent, $version)) {
			$browser_type = "Minimo";
			$browser_version = $version[1];
		} elseif (@preg_match('/K-Meleon\/(.*)/i', $useragent, $version)) {
			$browser_type = "K-Meleon";
			$browser_version = $version[1];
		} elseif (@preg_match('/Epiphany\/(.*)/i', $useragent, $version)) {
			$browser_type = "Epiphany";
			$browser_version = $version[1];
		} elseif (@preg_match('/Flock\/(.*)/i', $useragent, $version)) {
			$browser_type = "Flock";
			$browser_version = $version[1];
		} elseif (@preg_match('/Camino\/(.*)/i', $useragent, $version)) {
			$browser_type = "Camino";
			$browser_version = $version[1];
		} elseif (@preg_match('/Firebird\/(.*)/i', $useragent, $version)) {
			$browser_type = "Firebird";
			$browser_version = $version[1];
		} elseif (@preg_match('/Safari/i', $useragent)) {
			$browser_type = "Safari";
			$browser_version = "";
		} elseif (@preg_match('/avantbrowser/i', $useragent)) {
			$browser_type = "Avant Browser";
			$browser_version = "";
		} elseif (@preg_match('/America Online Browser [^0-9,.,a-z,A-Z]/i', $useragent)) {
			$browser_type = "Avant Browser";
			$browser_version = "";
		} elseif (@preg_match('/libwww/i', $useragent)) {
			if (@preg_match('/amaya/i', $useragent)) {
				$browser_type = "Amaya";
				$val = explode("/",stristr($useragent,"amaya"));
				$val = explode(" ", $val[1]);
				$browser_version = $val[0];
			} else {
				$browser_type = "Lynx";
				$val = explode("/",$useragent);
				$browser_version = $val[1];
			}
		} elseif (@preg_match('#Mozilla/([0-9].[0-9]{1,2})#i'. $useragent, $version)) {
			$browser_type = "Netscape";
			$browser_version = $version[1];
		}

		return $browser_type." ".$browser_version;
	}

	protected function robots($useragent)
	{
		$r_or=false;
		$remap_agents = array (
			'antabot'			=>	'antabot (private)',
			'aport'				=>	'Aport',
			'Ask Jeeves'		=>	'Ask Jeeves',
			'Asterias'			=>	'Singingfish Spider',
			'Baiduspider'		=>	'Baidu Spider',
			'Feedfetcher-Google'=>	'Feedfetcher-Google',
			'GameSpyHTTP'		=>	'GameSpy HTTP',
			'GigaBlast'			=>	'GigaBlast',
			'Gigabot'			=>	'Gigabot',
			'Accoona'			=>	'Google.com',
			'Googlebot-Image'	=>	'Googlebot-Image',
			'Googlebot'			=>	'Googlebot',
			'grub-client'		=>	'Grub',
			'gsa-crawler'		=>	'Google Search Appliance',
			'Slurp'				=>	'Inktomi Spider',
			'slurp@inktomi'		=>	'Hot Bot',

			'lycos'				=>	'Lycos.com',
			'whatuseek'			=>	'What You Seek',
			'ia_archiver'		=>	'Alexa',
			'is_archiver'		=>	'Archive.org',
			'archive_org'		=>	'Archive.org',

			'YandexBlog'		=>	'YandexBlog',
			'YandexSomething'	=>	'YandexSomething',
			'Yandex'			=>	'Yandex',
			'StackRambler'		=>	'Rambler',

			'WebAlta Crawler'	=>	'WebAlta Crawler',

			'Yahoo'				=>	'Yahoo',
			'zyborg@looksmart'	=>	'WiseNut',
			'WebCrawler'		=>	'Fast',
			'Openbot'			=>	'Openfind',
			'TurtleScanner'		=>	'Turtle',
			'libwww'			=>	'Punto',

			'msnbot'			=>  'MSN',
			'MnoGoSearch'		=>  'mnoGoSearch',
			'booch'				=>  'booch_Bot',
			'WebZIP'			=>	'WebZIP',
			'GetSmart'			=>	'GetSmart',
			'NaverBot'			=>	'NaverBot',
			'Vampire'			=>	'Net_Vampire',
			'ZipppBot'			=>	'ZipppBot',

			'W3C_Validator'		=>	'W3C Validator',
			'W3C_CSS_Validator'	=>	'W3C CSS Validator',
			);

		$remap_agents=array_change_key_case($remap_agents, CASE_LOWER);

		$pmatch_agents="";
		foreach ($remap_agents as $k => $v) {
			$pmatch_agents.=$k."|";
		}
		$pmatch_agents=substr_replace($pmatch_agents, '', strlen($pmatch_agents)-1, 1);

		if (preg_match( '/('.$pmatch_agents.')/i', $useragent, $match ))

			if (count($match)) {
				$r_or = @$remap_agents[strtolower($match[1])];
			}

		return $r_or;
	}

	protected function UserAgents()
	{
		if (!function_exists("dle_cache") || !($cache = dle_cache("useragents")))
		{
			$this->_db_disconnect();
			$this->db->query("SELECT * FROM " . USERPREFIX . "_uagents");
			$this->_db_connect();
			$useragents = array();
			while ($row = $this->db->get_row()) 
			{
				$useragents[] = $row;
			}

			if (function_exists('create_cache'))
			{
				create_cache("useragents", serialize($useragents));
			}
		}
		else
		{
			$useragents = @unserialize($cache);
		}

		return $useragents;
	}

	protected function findUserAgentID( $userAgent )
	{
		$uagentReturn = array( 'uagent_id'      => 0,
			'uagent_key'     => NULL,
			'uagent_name'    => NULL,
			'uagent_type'    => NULL,
			'uagent_version' => 0 );

		//-----------------------------------------
		// Test in the DB
		//-----------------------------------------

		$userAgentCache = $this->UserAgents();

		foreach( $userAgentCache as $key => $data )
		{
			$regex = str_replace( '#', '\\#', $data['uagent_regex'] );
			
			if ( ! preg_match( "#{$regex}#i", $userAgent, $matches ) )
			{
				continue;
			}
			else
			{
				//-----------------------------------------
				// Okay, we got a match - finalize
				//-----------------------------------------
				
				if ( $data['uagent_regex_capture'] )
				{
					$version = $matches[ $data['uagent_regex_capture'] ];
				}
				else
				{
					$version = 0;
				}
				
				$uagentReturn = array( 'uagent_id'      => $data['uagent_id'],
					'uagent_key'     => $data['uagent_key'],
					'uagent_name'    => $data['uagent_name'],
					'uagent_type'	=> $data['uagent_type'],
					'uagent_version' => intval( $version ) );

				break;
			}
		}
		
		return $uagentReturn;
	}

	protected function os($useragent)
	{
		$os = 'Unknown';
		if(strpos($useragent, "Win") !== false) 
		{
			if(strpos($useragent, "NT 7") !== false) $os = 'Windows Seven';
			if(strpos($useragent, "NT 6.1") !== false) $os = 'Windows Seven';
			if(strpos($useragent, "NT 6.0") !== false) $os = 'Windows Vista';
			if(strpos($useragent, "NT 5.2") !== false) $os = 'Windows Server 2003 ??? XPx64';
			if(strpos($useragent, "NT 5.1") !== false || strpos($useragent, "Win32") !== false || strpos($useragent, "XP")) $os = 'Windows XP';
			if(strpos($useragent, "NT 5.0") !== false) $os = 'Windows 2000';
			if(strpos($useragent, "NT 4.0") !== false || strpos($useragent, "3.5") !== false) $os = 'Windows NT';
			if(strpos($useragent, "Me") !== false) $os = 'Windows Me';
			if(strpos($useragent, "98") !== false) $os = 'Windows 98';
			if(strpos($useragent, "95") !== false) $os = 'Windows 95';
		}

		if(strpos($useragent, "Linux")    !== false
			|| strpos($useragent, "Lynx")     !== false
			|| strpos($useragent, "Unix")     !== false) $os = 'Linux';
			if(strpos($useragent, "Macintosh")!== false
				|| strpos($useragent, "PowerPC")) $os = 'macOS';
				if(strpos($useragent, "OS/2")!== false) $os = 'OS/2';
			if(strpos($useragent, "BeOS")!== false) $os = 'BeOS';

		return $os;
	}

	protected function changeend($value,$v1,$v2,$v3)
	{
		$endingret="";
		if (substr($value,-1)==1) $endingret = $v1;
		if (substr($value,-1)==2) $endingret = $v2;
		if (substr($value,-1)==3) $endingret = $v2;
		if (substr($value,-1)==4) $endingret = $v2;
		if (substr($value,-2)==11) $endingret = $v3;
		if (substr($value,-2)==12) $endingret = $v3;
		if (substr($value,-2)==13) $endingret = $v3;
		if (substr($value,-2)==14) $endingret = $v3;
		if (empty($endingret)) $endingret = $v3;

		return $endingret;
	}

	protected function timeagos($timestamp)
	{
		$current_time = time();
		$difference = $current_time - $timestamp;

		$lengths = array(1, 60, 3600, 86400, 604800, 2630880, 31570560, 315705600);

		for ($val = sizeof($lengths) - 1; ($val >= 0) && (($number = $difference / $lengths[$val]) <= 1); $val--);

			if ($val < 0) $val = 0;
		$new_time = $current_time - ($difference % $lengths[$val]);
		$number = floor($number);

		switch ($val) {
			case 0: $stamp = $this->changeend($number,$this->lang['stamp01'],$this->lang['stamp02'],$this->lang['stamp03']); break;
			case 1: $stamp = $this->changeend($number,$this->lang['stamp11'],$this->lang['stamp12'],$this->lang['stamp13']); break;
			case 2: $stamp = $this->changeend($number,$this->lang['stamp21'],$this->lang['stamp22'],$this->lang['stamp23']); break;
			case 3: $stamp = $this->changeend($number,$this->lang['stamp31'],$this->lang['stamp32'],$this->lang['stamp33']); break;
			case 4: $stamp = $this->changeend($number,$this->lang['stamp41'],$this->lang['stamp42'],$this->lang['stamp43']); break;
			case 5: $stamp = $this->changeend($number,$this->lang['stamp51'],$this->lang['stamp52'],$this->lang['stamp53']); break;
			case 6: $stamp = $this->changeend($number,$this->lang['stamp61'],$this->lang['stamp62'],$this->lang['stamp63']); break;
			case 5: $stamp = $this->changeend($number,$this->lang['stamp71'],$this->lang['stamp72'],$this->lang['stamp73']); break;
		}
		$text = sprintf("%d %s ", $number, $stamp);
		if (($val >= 1) && (($current_time - $new_time) > 0)){
			$text .= $this->timeagos($new_time);
		}

		return $text;
	}

	public function block_online(dle_template &$tpl)
	{
		if (!$this->config['allow_module'] || !$this->config['allow_online_block'])
		{
			return false;
		}

		if ((int)$this->config['block_online_cache_time'] && 
			file_exists(ENGINE_DIR . "/cache/block_online.tmp") &&
			(time() - filemtime(ENGINE_DIR . "/cache/block_online.tmp")) > (int)$this->config['block_online_cache_time'] &&
			$cache = dle_cache("block_online")
			)
		{
			$tpl->result['block_online'] = $cache;
			return true;
		}

		$this->_db_connect();

		$this->db->query("SELECT member_id, ip_address, running_time, location_data, browser, member_name FROM " . IPB_PREFIX . "core_sessions WHERE running_time>".(time()-$this->config['online_time']));

		$users = $robots = $onl_onlinebots = array(); $guests = $count_user = $count_robots = 0;
		while ($user = $this->db->get_row())
		{
			foreach ($user as &$value)
			{
				$this->_convert_charset($value, true);
			}

			if($user['member_id']==0) 
			{
				$current_robot = $this->robots($user['browser']);
				if ($current_robot!="")
				{
					if ($onl_onlinebots[$current_robot]['lastactivity']<$user['running_time'])
					{
						$robots[$current_robot]['name']=$current_robot;
						$robots[$current_robot]['lastactivity']=$user['running_time'];
						$robots[$current_robot]['host']=$user['ip_address'];
						$robots[$current_robot]['location']=$user['location_data'];
					}
				}
				else
					$guests++;
			}
			else
			{
				if ($users[$user['member_id']]['lastactivity']<$user['running_time'])
				{
					$users[$user['member_id']]['username']=$user['member_name'];
					$users[$user['member_id']]['lastactivity']=$user['running_time'];
					$users[$user['member_id']]['useragent']=$user['browser'];
					$users[$user['member_id']]['host']=$user['ip_address'];
					$users[$user['member_id']]['location']=$user['location_data'];
				}
			}
		}

		$location_array = array("%addcomments%" => $this->lang['paddcomments'],
			"%readnews%"	=> $this->lang['preadnews'],
			"%incategory%"	=> $this->lang['pincategory'],
			"%posin%"		=> $this->lang['pposin'],
			"%mainpage%"	=> $this->lang['pmainpage'],
			"%view_pofile%"	=> $this->lang['view_profile'],
			"%newposts%"	=> $this->lang['newposts'],
			"%view_stats%"	=> $this->lang['view_stats']);
		if (count($users))
		{
			foreach ($users AS $id=>$value)
			{
				$user_array[$value['username']]['desc'] = '';
				if(@$GLOBALS['member_id']['user_group'] == 1)
				{
					$user_array[$value['username']]['desc'] .= $this->lang['os'].$this->os($value['useragent']).'<br />' . $this->lang['browser'].$this->browser($users[$id]['useragent']).'<br />' . '<b>IP:</b>&nbsp;'.$users[$id]['host'].'<br />';
				}

				$user_array[$value['username']]['desc'] .= $this->lang['was'].$this->timeagos($users[$id]['lastactivity']).$this->lang['back'].'<br />' . $this->lang['location'];
				if (preg_match("'%(.*?)%'si", $users[$id]['location']))
				{
					foreach ($location_array as $find => $replace)
					{
						$users[$id]['location'] = str_replace($find, $replace, $users[$id]['location']);
					}
				}
				else 
					$users[$id]['location'] = $this->lang['pforum'];
				$user_array[$value['username']]['desc'] .= $users[$id]['location']."<br/>";
				$user_array[$value['username']]['id'] = $id;
				$count_user++;
			}
		}

		if (count($robots))
		{
			foreach ($robots AS $name=>$value)
			{
				if(!empty($GLOBALS['member_id']['user_group']) && $GLOBALS['member_id']['user_group'] == 1)
					$robot_array[$name]= $this->lang['os'].$this->os($robots[$name]['useragent']).'<br />' . $this->lang['browser'].$this->browser($robots[$name]['useragent']).'<br />' . '<b>IP:</b>&nbsp;'.$robots[$name]['host'].'<br />';

				$robot_array[$name] .= $this->lang['was'].$this->timeagos($robots[$name]['lastactivity']).$this->lang['back'].'<br />' . $this->lang['location'];
				if (preg_match("'%(.*?)%'si", $robots[$name]['location']))
				{
					foreach ($location_array as $find => $replace)
					{
						$robots[$name]['location'] = str_replace($find, $replace, $robots[$name]['location']);
					}
				}
				else 
					$robots[$name]['location'] = $this->lang['pforum'];
				$robot_array[$name] .= $robots[$name]['location']."<br/>";
				$count_robots++;
			}
		}

		$users = ""; $i=0;
		if (count($user_array))
		{
			foreach ($user_array as $name=>$desc)
			{
				if ($i) $users .= $this->config['separator'];
				$desc['desc'] = htmlspecialchars($desc['desc'], ENT_QUOTES);
				$users .= "<li><a data-toggle=\"popover\" title=\"Просмотр {$name}\" data-content=\"{$desc['desc']}\" href=\"{$this->ipb_config['base_url']}index.php?app=core&module=members&controller=profile&id={$desc['id']}\">{$name}</a></li>";
				$i++;
			}
		}
		if (!count($robot_array) AND !count($user_array)) {
			$users = $this->lang['notusers'];
		}

		$robots = ""; $i = 0;
		if (count($robot_array))
		{
			foreach ($robot_array as $name=>$desc)
			{
				if ($i OR count($user_array) > 0) $seperators = $this->config['separator'];
				$desc = htmlspecialchars($desc, ENT_QUOTES);
				$robots .= "<li><span data-content=\"{$desc}\" data-toggle=\"popover\" style=\"cursor:hand;\" >".$seperators.$name."</span></li>";
				$i++;
			}
		}

		$tpl->load_template('dle_ipb/block_online.tpl');
		$tpl->set('{users}',$count_user);
		$tpl->set('{guest}',$guests);
		$tpl->set('{robots}',$count_robots);
		$tpl->set('{all}',($count_user+$guests+$count_robots));
		$tpl->set('{userlist}',$users);
		$tpl->set('{botlist}',$robots);
		$tpl->compile('block_online');
		$tpl->clear();

		$this->_db_disconnect();

		if ((int)$this->config['block_online_cache_time'])
		{
			create_cache("block_online", $tpl->result['block_online']);
		}

		return true;
	}

	public function GetNewsLink($row, $category_id)
	{
		if( $GLOBALS['config']['allow_alt_url'] == "yes" ) {
			
			if( $GLOBALS['config']['seo_type'] == 1 OR $GLOBALS['config']['seo_type'] == 2  ) {
				
				if( $category_id and $GLOBALS['config']['seo_type'] == 2 ) {
					
					$full_link = $GLOBALS['config']['http_home_url'] . get_url( $row['category'] ) . "/" . $row['id'] . "-" . $row['alt_name'] . ".html";

				} else {
					
					$full_link = $GLOBALS['config']['http_home_url'] . $row['id'] . "-" . $row['alt_name'] . ".html";

				}

			} else {
				
				$full_link = $GLOBALS['config']['http_home_url'] . date( 'Y/m/d/', $row['date'] ) . $row['alt_name'] . ".html";
			}

		} else {
			
			$full_link = $GLOBALS['config']['http_home_url'] . "index.php?newsid=" . $row['id'];

		}

		return $full_link;
	}

	public function link_forum(array &$row, dle_template &$tpl)
	{
		$categories = explode(",", $row['category']);
		foreach ($categories as $category)
		{
			if (intval($this->config['forumid'][$category]))
			{
				$cat_id = $category;
				break;
			}
		}
		if (!$this->config['goforum'] || 
			!$this->config['allow_module'] || 
			!$cat_id || 
			(!$this->config['show_no_reginstred'] && !$GLOBALS['is_logged'])
			)
		{
			return $tpl->set('{link_on_forum}', "");
		}
		if (!intval($GLOBALS['newsid']))
		{
			if (!$this->config['show_short'])
			{
				return $tpl->set('{link_on_forum}', "");
			}
			elseif ($this->config['allow_count_short'])
			{
				$this->config['show_count'] = 1;
			}
			else 
			{
				$this->config['show_count'] = 0;
			}
		}

		$link_on_forum = $this->config['link_on_forum'];
		
		if ($this->config['show_count'])
		{
			$this->_db_connect();

			switch ($this->config['link_title'])
			{
				case "old":
				$title_forum = preg_replace('/{Post_name}/', $row['title'], $this->config['name_post_on_forum']);
				$title_forum = $this->db->safesql($title_forum);
				if ($title_forum == "") return;
				break;

				case "title":
				$title_forum = $this->db->safesql(stripslashes($row['title']));
				break;

				default:
				$this->_db_disconnect();
				return false;
				break;
			}

			$this->_convert_charset($title_forum);

			$topic = $this->db->super_query("SELECT tid, posts FROM ". IPB_PREFIX ."forums_topics WHERE title='$title_forum' AND state='open'");

			if (empty($topic['tid']))
			{
				$link_on_forum = preg_replace("'\[count\](.*?)\[/count\]'si", "", $link_on_forum);
				$count = 0;
			}
			else 
			{
				$count = $topic['posts'];
				$link_on_forum = preg_replace("'\[count\](.*?)\[/count\]'si", "\\1", $link_on_forum);
			}
			$this->_db_disconnect();
		}
		else 
			$link_on_forum = preg_replace("'\[count\](.*?)\[/count\]'si", "", $link_on_forum);

		$link_on_forum = str_replace("{count}", $count, $link_on_forum);
		$link_on_forum = str_replace('{link_on_forum}',(($GLOBALS['config']['allow_alt_url'] == "yes")?$GLOBALS['config']['http_home_url']."goforum/post-".$row['id']."/":$GLOBALS['PHP_SELF']."?do=goforum&postid=".$row['id']), $link_on_forum);
		
		$tpl->set('{link_on_forum}', $link_on_forum);

		return true;
	}

	public function _parse_post($text_forum, $id)
	{
		require_once ENGINE_DIR . '/classes/parse.class.php';
		$parse = new ParseFilter( Array (), Array (), 1, 1 );

		function build_thumb(ParseFilter &$parse, $gurl = "", $url = "", $align = "")
		{
			$url = trim( $url );
			$gurl = trim( $gurl );
			$option = explode( "|", trim( $align ) );

			$align = $option[0];

			if( $align != "left" and $align != "right" ) $align = '';

			$url = $parse->clear_url( urldecode( $url ) );
			$gurl = $parse->clear_url( urldecode( $gurl ) );

			if( $gurl == "" or $url == "" ) return;

			if( $align == '' )
			{
				return "[$align][url=\"$gurl\"][img]{$url}[/img][/url][/$align]";
			}
			else
			{
				return "[url=\"$gurl\"][img]{$url}[/img][/url]";
			}

		}

		function decode_img($img, $txt) 
		{
			$txt = stripslashes( $txt );
			$align = false;

			if( strpos( $txt, "align=\"" ) !== false ) {

				$align = preg_replace( "#(.+?)align=\"(.+?)\"(.*)#is", "\\2", $txt );
			}

			if( $align != "left" and $align != "right" ) $align = false;

			if($align)
			{
				return "[$align][img]" . $img . "[/img][/$align]";
			}
			else
			{
				return "[img]" . $img . "[/img]";
			}
		}

		if ( strpos( $text_forum, "[attachment=" ) !== false)
		{
			$this->_db_disconnect();
			$text_forum = show_attach($text_forum, $id);
			$this->_db_connect();
		}

		$text_forum = preg_replace('#\[.+?\]#', '', $text_forum);
		$text_forum = preg_replace( "#<img src=[\"'](\S+?)['\"](.+?)>#ie", "decode_img('\\1', '\\2')", $text_forum);

		$text_forum = $parse->decodeBBCodes( $text_forum, false );
		$text_forum = nl2br(preg_replace('#<.+?>#s', '', $text_forum));

		$text_forum = str_replace('leech', 'url', $text_forum);
		$text_forum = preg_replace( "#\[video\s*=\s*(\S.+?)\s*\]#ie", "\$parse->build_video('\\1')", $text_forum );
		$text_forum = preg_replace( "#\[audio\s*=\s*(\S.+?)\s*\]#ie", "\$parse->build_audio('\\1')", $text_forum );
		$text_forum = preg_replace( "#\[flash=([^\]]+)\](.+?)\[/flash\]#ies", "\$parse->build_flash('\\1', '\\2')", $text_forum );
		$text_forum = preg_replace( "#\[youtube=([^\]]+)\]#ies", "\$parse->build_youtube('\\1')", $text_forum );
		$text_forum = preg_replace( "'\[thumb\]([^\[]*)([/\\\\])(.*?)\[/thumb\]'ie", "build_thumb(\$parse, '\$1\$2\$3', '\$1\$2thumbs\$2\$3')", $text_forum );
		$text_forum = preg_replace( "'\[thumb=(.*?)\]([^\[]*)([/\\\\])(.*?)\[/thumb\]'ie", "build_thumb(\$parse, '\$2\$3\$4', '\$2\$3thumbs\$3\$4', '\$1')", $text_forum );

		$text_forum = preg_replace('#<!--.+?-->#s', '', $text_forum);

		return $text_forum;
	}

	public function GoForum()
	{
		$news_id = intval($_REQUEST['postid']);

		if (!$news_id) 
		{
			die("Hacking attempt!");
		}

		if (version_compare($GLOBALS['config']['version'], 9.6, ">="))
		{
			$title = $this->db->super_query("SELECT * FROM " . PREFIX . "_post p
				INNER JOIN " . PREFIX . "_post_extras e
				ON e.news_id=p.id
				WHERE id='$news_id'");
		}
		else
		{
			$title = $this->db->super_query("SELECT * FROM " . PREFIX . "_post WHERE id='$news_id'");
		}

		$categories = explode(",", $title['category']); $forum_id = 0;

		foreach ($categories as $category)
		{
			if (intval($this->config['forumid'][$category]))
			{
				$category_id = $category;
				$forum_id = $this->config['forumid'][$category];
				break;
			}
		}

		if (!$forum_id)
		{
			return false;
		}

		$this->_db_connect();

		switch ($this->config['link_title'])
		{
			case "old":
			$title_forum = preg_replace('/{Post_name}/',$title['title'], $this->config['name_post_on_forum']);
			$title_forum = $this->db->safesql(stripslashes($title_forum));
			break;

			case "title":
			$title_forum = $this->db->safesql(stripslashes($title['title']));
			break;

			default:
			$this->_db_disconnect();
			return false;
			break;
		}

		$this->_convert_charset($title_forum);

		$isset_post = $this->db->super_query("SELECT tid FROM ". IPB_PREFIX ."forums_topics WHERE title='$title_forum' AND state='open'");
		if ($isset_post['tid'] != "")
		{
			header("Location:{$this->ipb_config['base_url']}index.php?app=forums&module=forums&controller=topic&id={$isset_post['tid']}&view=getnewpost");
			exit;
		}
		
		switch ($this->config['link_text'])
		{
			case "full":
			if (strlen($title['full_story']) > 10)
				$text_forum = $title['full_story'];
			else 
				$text_forum = $title['short_story'];

			$text_forum = stripslashes($text_forum);
			$news_seiten = explode("{PAGEBREAK}", $text_forum);
			$text_forum = $news_seiten[0];
			$text_forum = preg_replace('#(\A[\s]*<br[^>]*>[\s]*|'
				.'<br[^>]*>[\s]*\Z)#is', '', $text_forum);
			if (count($news_seiten) > 1)
			{
				$text_forum .= "<a href='".(($GLOBALS['config']['allow_alt_url'] == "yes")?$GLOBALS['config']['http_home_url'].date('Y/m/d/', $title['date']).$title['alt_name'].".html":"/index.php?newsid=".$title['id'])."' >".$this->lang['view_full']."</a>";
			}
			elseif ($this->config['link_on_news'])
			{
				$this->config['text_post_on_forum'] = preg_replace('/{post_name}/i',$title['title'], $this->config['text_post_on_forum']);
				$this->config['text_post_on_forum'] = preg_replace('/{post_link}/i', $this->GetNewsLink($title, $category_id), $this->config['text_post_on_forum']);
				$text_forum .= "\n" . $this->config['text_post_on_forum'];
			}

			if ($title['allow_br'])
			{
				$text_forum = $this->_parse_post($text_forum, $title['id']);
			}
			$text_forum = $this->db->safesql($text_forum);
			break;

			case "short":
			$text_forum = $title['short_story'];
			$text_forum = stripslashes($text_forum);
			if ($this->config['link_on_news'])
			{
				$this->config['text_post_on_forum'] = preg_replace('/{post_name}/i',$title['title'], $this->config['text_post_on_forum']);
				$this->config['text_post_on_forum'] = preg_replace('/{post_link}/i', $this->GetNewsLink($title, $category_id), $this->config['text_post_on_forum']);
				$text_forum .= "\n" . $this->config['text_post_on_forum'];
			}

			if ($title['allow_br'])
			{
				$text_forum = $this->_parse_post($text_forum, $title['id']);
			}

			$text_forum = $this->db->safesql($text_forum);
			break;

			case "old":
			$text_forum = preg_replace('/{Post_name}/',$title['title'], $this->config['text_post_on_forum']);
			$text_forum = preg_replace('/{post_link}/', $this->GetNewsLink($title, $category_id), $text_forum);
			$text_forum = $this->db->safesql(stripslashes($text_forum));
			break;

			default:
			$this->_db_disconnect();
			return false;
			break;
		}

		switch ($this->config['link_user'])
		{
			case "old":
			$user = $this->db->safesql($this->config['postusername']);
			if ($user == "")
			{
				$this->_db_disconnect();
				return false;
			}
			$user_id = intval($this->config['postuserid']);

			if (!$user_id)
			{
				$user_id = 0;
			}
			break;

			case "author":
			$autor = $this->_convert_charset($title['autor']);
			$user_info = $this->db->super_query("SELECT member_id FROM ". IPB_PREFIX ."core_members WHERE name='".$this->db->safesql($autor)."' LIMIT 1");
			if (empty($user_info['member_id']))
			{
				$user = $this->db->safesql($this->config['postusername']);
				$user_id = 0;
				if ($user == "")
				{
					$this->_db_disconnect();
					return false;
				}
			}
			else 
			{
				$user_id = $user_info['member_id'];
				$user = $this->db->safesql(stripslashes($title['autor']));
			}
			break;

			case "cur_user":
			if (empty($this->member['member_id']))
			{
				if ($GLOBALS['member_id']['name'])
				{
					$this->member = $this->db->super_query("SELECT * FROM ". IPB_PREFIX ."core_members WHERE name='".$this->db->safesql($GLOBALS['member_id']['name'])."' LIMIT 1");
				}
				elseif (!empty($_COOKIE['dle_name']))
				{
					$this->member = $this->db->super_query("SELECT * FROM ". IPB_PREFIX ."core_members WHERE name='".$this->db->safesql($_COOKIE['dle_name'])."' LIMIT 1");
				}
				elseif (!empty($_COOKIE['dle_user_id']))
				{
					$this->_db_disconnect();
					$user_name = $this->db->super_query("SELECT name FROM " . USERPREFIX . "_users WHERE user_id='" . intval($_COOKIE['dle_user_id']) . "'");
					$this->_db_connect();
					$this->member = $this->db->super_query("SELECT * FROM ". IPB_PREFIX ."core_members WHERE name='".$this->db->safesql(($user_name['name']))."' LIMIT 1");
				}
			}

			if (empty($this->member['member_id']))
			{
				if (!$this->config['postusername'])
				{
					$this->_db_disconnect();
					return ;
				}
				$user_id = intval($this->config['postuserid']);

				if (!$user_id)
				{
					$user_id = 0;
				}

				$user = $this->config['postusername'];
			}
			else 
			{
				$user_id = $this->member['member_id'];
				$user = $this->member['name'];
			}

			$user = $this->db->safesql($user);
			break;

			default:
			$this->_db_disconnect();
			return false;
			break;
		}

		$forum = $this->db->super_query("SELECT id FROM " . IPB_PREFIX . "forums_forums WHERE id='$forum_id'");
		if (empty($forum['id']))
		{
			$this->_db_disconnect();
			return false;
		}
		

		$this->_convert_charset($user);
		$this->_convert_charset($text_forum);
		$post_htmlstate = 0;

		if ($title['allow_br'])
		{
			$post_htmlstate = 2;
		}

		$this->db->query("INSERT INTO ". IPB_PREFIX ."forums_topics (title, start_date, forum_id, state, posts, last_post, starter_name, last_poster_name, poll_state, last_vote, views, approved, author_mode, pinned, starter_id, last_poster_id) VALUES ('$title_forum', '".time()."', '$forum_id', 'open', '0', '".time()."', '$user', '$user', '0', '0', '1', '1', '0', '0', '$user_id', '$user_id')");
		$tp_id = $this->db->insert_id();
		$this->db->query("INSERT INTO ". IPB_PREFIX ."forums_posts (author_name, author_id, ip_address, post_date, post, topic_id, new_topic, post_htmlstate) VALUES ('$user', '$user_id', '".$this->db->safesql($this->ip())."', '".time()."', '$text_forum', '$tp_id', '1', $post_htmlstate)");
		$post_id = $this->db->insert_id();
		$this->db->query("UPDATE " . IPB_PREFIX . "forums_topics SET topic_firstpost='$post_id' WHERE tid='$tp_id'");
		$this->db->query("UPDATE " . IPB_PREFIX . "forums_forums SET last_poster_name='$user', last_poster_id='$user_id', last_title='$title_forum', last_id='$tp_id', topics=topics+1 WHERE id='".$this->db->safesql($forum_id)."'");
		header("Location:{$this->ipb_config['base_url']}index.php?app=forums&module=forums&controller=topic&id=$tp_id&view=getnewpost");
		exit();
	}
}

$ipb = new ipb_member($db);
?>