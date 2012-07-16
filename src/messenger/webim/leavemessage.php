<?php
/*
 * Copyright 2005-2013 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once('libs/common.php');
require_once('libs/chat.php');
require_once('libs/expand.php');
require_once('libs/groups.php');
require_once('libs/captcha.php');
require_once('libs/notify.php');

$errors = array();
$page = array();

function store_message($name, $email, $info, $message,$groupid,$referrer) {
	global $state_left, $current_locale, $kind_for_agent, $kind_user;
	$remoteHost = get_remote_host();
	$userbrowser = $_SERVER['HTTP_USER_AGENT'];
	$visitor = visitor_from_request();
	$thread = create_thread($groupid,$name,$remoteHost,$referrer,$current_locale,$visitor['id'], $userbrowser,$state_left);
	if( $referrer ) {
		post_message_($thread['threadid'],$kind_for_agent,getstring2('chat.came.from',array($referrer)));
	}
	if($email) {
		post_message_($thread['threadid'],$kind_for_agent,getstring2('chat.visitor.email',array($email)));
	}
	if($info) {
		post_message_($thread['threadid'],$kind_for_agent,getstring2('chat.visitor.info',array($info)));
	}
	post_message_($thread['threadid'],$kind_user,$message,$name);
}

$groupid = "";
$groupname = "";
$group = NULL;
if(Settings::get('enablegroups') == '1') {
	$groupid = verifyparam( "group", "/^\d{1,8}$/", "");
	if($groupid) {
		$group = group_by_id($groupid);
		if(!$group) {
			$groupid = "";
		} else {
			$groupname = get_group_name($group);
		}
	}
}

$email = getparam('email');
$visitor_name = getparam('name');
$message = getparam('message');
$info = getparam('info');
$referrer = urldecode(getparam("referrer"));

if( !$email ) {
	$errors[] = no_field("form.field.email");
} else if( !$visitor_name ) {
	$errors[] = no_field("form.field.name");
} else if( !$message ) {
	$errors[] = no_field("form.field.message");
} else {
	if( !is_valid_email($email)) {
		$errors[] = wrong_field("form.field.email");
	}
}

if(Settings::get("enablecaptcha") == "1" && can_show_captcha()) {
	$captcha = getparam('captcha');
	$original = isset($_SESSION["mibew_captcha"]) ? $_SESSION["mibew_captcha"] : "";
	if(empty($original) || empty($captcha) || $captcha != $original) {
	  $errors[] = getlocal('errors.captcha');
	}
	unset($_SESSION['mibew_captcha']);
}

if( count($errors) > 0 ) {
	setup_leavemessage($visitor_name,$email,$message,$groupid,$groupname,$info,$referrer,can_show_captcha());
	setup_logo($group);
	expand("styles/dialogs", getchatstyle(), "leavemessage.tpl");
	exit;
}

$message_locale = Settings::get('left_messages_locale');
if(!locale_exists($message_locale)) {
	$message_locale = $home_locale;
}

store_message($visitor_name, $email, $info, $message, $groupid, $referrer);

$subject = getstring2_("leavemail.subject", array($visitor_name), $message_locale);
$body = getstring2_("leavemail.body", array($visitor_name,$email,$message,$info ? "$info\n" : ""), $message_locale);

if (isset($group) && !empty($group['vcemail'])) {
	$inbox_mail = $group['vcemail'];
} else {
	if (! is_null($group['parent'])) {
		$parentgroup = group_by_id($group['parent']);
		if ($parentgroup && !empty($parentgroup['vcemail'])) {
			$inbox_mail = $parentgroup['vcemail'];
		}
	}
}

if (empty($inbox_mail)) {
	$inbox_mail = Settings::get('email');
}

if($inbox_mail) {
	webim_mail($inbox_mail, $email, $subject, $body);
}

setup_logo($group);
expand("styles/dialogs", getchatstyle(), "leavemessagesent.tpl");
?>