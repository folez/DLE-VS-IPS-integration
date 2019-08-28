# DLE-VS-IPS-integration
Integration auth,register DLE and IPS 4.X
---
### Changes to files should not be made until the module is installed.
**Installation guide**
1. Installation of integration is performed only on installed DLE and IPS scripts
2. We load all the files from the dle_uploads folder to the DLL root on the server in the binary mode, they have the same directory structure as DLE.
3. Copy the conf_global.php file from the root of the forum to the root of the DLL
4. The contents of the templates / Default folder (* .tpl files) can be copied into the folder with your template, you can change them to your template, by default they are made for the Default template.
5. Run the installation file http: //www.your_domain/install.php, then follow its instructions. Do not skip to the next steps until you finish using the installation wizard. You can not change the files, each change is commented, if you do not intend to use this or that possibility, you can not make changes for it.

> If the forum and the DLE use different domains or the forum is on a subdomain, then this setting is not required. Otherwise, here is the name of your domain where the forum stands with a dot in front and without a slash, the rest does not touch anything.
---
1. Create a file in the root of the forum constants.php (if there is none)
2. Add this code
```php
define( 'COOKIE_DOMAIN', '.your_domain_forum.com' );
```
---
## General Authorization **File engine/modules/sitelogin.php**
##### Integration module connection (Required)
Find this code
```php
if( ! defined( 'DATALIFEENGINE' ) ) {
	die( "Hacking attempt!" );
}
```
Add below
```php
/* ----------- DLE + IP.Board v2.0. ----------- */
include(ROOT_DIR . "/dle_vs_ipboard.php");
/* ----------- DLE + IP.Board v2.0. ----------- */
```
##### Logout
Find code
```php
$dle_user_id = "";
$dle_password = "";
```
Add earlier
```php
/* ----------- DLE + IP.Board v2.0. ----------- */
$ipb->logout();
/* ----------- DLE + IP.Board v2.0. ----------- */
```
##### Authorization
Find
```php
if( is_md5hash( $member_id['password'] ) ) {
				
	if($member_id['password'] == md5( md5($_POST['login_password']) ) ) {
		$is_logged = true;
	}

} else {

	if(password_verify($_POST['login_password'], $member_id['password'] ) ) {
		$is_logged = true;
	}

}
```
Replace by
```php
if( is_md5hash( $member_id['password'] ) ) {
				
	if($member_id['password'] == md5( md5($_POST['login_password']) ) ) {
		$is_logged = true;
	}
	
} else {
	
	if ($member_id['pass_salt'] == NULL) {
		if(password_verify($_POST['login_password'], $member_id['password'] ) ) {
			$new_salt = generateSalt();
			$new_pass = encryptedPassword( $_POST['login_password'], $new_salt );
			$db->query( "UPDATE ".USERPREFIX."_users SET password='{$new_pass}', pass_salt='{$new_salt}'" );
			$is_logged = true;
		}
	} else {
		if ( encryptedPassword( $_POST['login_password'], $member_id['pass_salt']) == $member_id['password']  ) {
			$is_logged = true;
		}
	}
	
}
```
Find
```php
if ( password_needs_rehash($member_id['password'], PASSWORD_DEFAULT) ) {

	if (version_compare($config['version_id'], '11.2', '>=')) {

		$member_id['password'] = password_hash($_POST['login_password'], PASSWORD_DEFAULT);
		
		if( !$member_id['password'] ) {
			die("PHP extension Crypt must be loaded for password_hash to function");
		}
		
		$new_pass_hash = "password='".$db->safesql($member_id['password'])."', ";
		
	} else $new_pass_hash = "";
	
} else $new_pass_hash = "";
```
Replace
```php
$new_pass_hash = "";
```
At the end of the file before ?>
Add
```php
/* ----------- DLE + IP.Board v2.0. ----------- */
$ipb->login($member_id);
/* ----------- DLE + IP.Board v2.0. ----------- */
```
***
## General Registration *** File engine/modules/register.php ***
##### Password Hashing
Find
```php
$regpassword = $db->safesql( password_hash($regpassword, PASSWORD_DEFAULT) );
```
Replace
```php
/* ----------- DLE + IP.Board v2.0. ----------- */
$salt_reg = generateSalt();
$pass_reg_hash = encryptedPassword($regpassword, $salt_reg);
$regpassword = $db->safesql($pass_reg_hash);
/* ----------- DLE + IP.Board v2.0. ----------- */
```
>This function performs hashing of passwords in a new form, and is required for insertion if you want a general registration and a general profile!
##### Registration
Find
```php
$db->query( "INSERT INTO " . USERPREFIX . "_users (name, password, email, reg_date, lastdate, user_group, info, signature, favorites, xfields, logged_ip, hash) VALUES ('{$name}', '{$regpassword}', '{$email}', '{$add_time}', '{$add_time}', '{$config['reg_group']}', '', '', '', '', '{$_IP}', '{$hash}')" );
$id = $db->insert_id();
```
Replace
```php
$db->query( "INSERT INTO " . USERPREFIX . "_users (name, password, pass_salt, email, reg_date, lastdate, user_group, info, signature, favorites, xfields, logged_ip) VALUES ('$name', '$regpassword', '$salt_reg', '$email', '$add_time', '$add_time', '" . $config['reg_group'] . "', '', '', '', '', '" . $_IP . "')" );
$id = $db->insert_id();

/* ----------- DLE + IP.Board v2.0. ----------- */
$ipb->CreateMember($name, $regpassword, $salt_reg, $email, $add_time);
/* ----------- DLE + IP.Board v2.0. ----------- */
```
***
## General password reset *** File engine/modules/lostpassword.php ***
##### Change the password on the forum, after restoring to the DLL
Find
```php
$new_pass_hash = password_hash($new_pass, PASSWORD_DEFAULT);
```
Replace
```php
/* ----------- DLE + IP.Board v2.0. ----------- */
$salt_lost		= generateSalt();
$new_pass_hash		= encryptedPassword($new_pass_hash, $salt_lost);
/* ----------- DLE + IP.Board v2.0. ----------- */
```
>This function encrypts passwords in a new form, and is required!
Find
```php
$db->query( "UPDATE " . USERPREFIX . "_users SET password='" . $db->safesql($new_pass_hash) . "', allowed_ip = '' WHERE user_id='{$douser}'" );
$db->query( "DELETE FROM " . USERPREFIX . "_lostdb WHERE lostname='$douser'" );
```
Replace
```php
$db->query( "UPDATE " . USERPREFIX . "_users SET password='" . $db->safesql($new_pass_hash) . "', pass_salt='".$salt_lost."' allowed_ip = '' WHERE user_id='{$douser}'" );
$db->query( "DELETE FROM " . USERPREFIX . "_lostdb WHERE lostname='$douser'" );

/* ----------- DLE + IP.Board v2.0. ----------- */
$ipb->LostPassword($username, $new_pass, $salt_lost);
/* ----------- DLE + IP.Board v2.0. ----------- */
```

## Modifying the profile on the forum, if you change the DLL The *** engine/modules/profile.php file ***

### New kind of passwords
Find
```php
$password1 = $db->safesql( password_hash($password1, PASSWORD_DEFAULT) );
```
Replace
```php
/* ----------- DLE + IP.Board v2.0. ----------- */
$salt_profile		= generateSalt();
if ($member_id['pass_salt'] != '') {
$salt_profile	= $member_id['pass_salt'];
}
$password1			= encryptedPassword($password1, $salt_profile);
$password1 			= $db->safesql( $password1 );
/* ----------- DLE + IP.Board v2.0. ----------- */
```
##### Updating user information
Find
```php
$db->query( $sql_user );
```
Replace
```php
/* ----------- DLE + IP.Board v2.0. ----------- */
$ipb->UpdateProfile($row['name'], $email, $_POST['password1'], $salt_profile, $info);
/* ----------- DLE + IP.Board v2.0. ----------- */

$db->query( $sql_user );
````
***

© Integration developed by FoLez
---
My WebSite <https://web-folez.ru/>
