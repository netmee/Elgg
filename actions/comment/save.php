<?php
/**
 * Action for adding and editing comments
 *
 * @package Elgg.Core
 * @subpackage Comments
 */

$entity_guid = (int) get_input('entity_guid', 0, false);
$comment_guid = (int) get_input('comment_guid', 0, false);
$comment_text = get_input('generic_comment');

if (empty($comment_text)) {
	register_error(elgg_echo("generic_comment:blank"));
	forward(REFERER);
}

if ($comment_guid) {
	// Edit an existing comment
	$comment = get_entity($comment_guid);

	if (!elgg_instanceof($comment, 'object', 'comment')) {
		register_error(elgg_echo("generic_comment:notfound"));
		forward(REFERER);
	}
	if (!$comment->canEdit()) {
		register_error(elgg_echo("actionunauthorized"));
		forward(REFERER);
	}

	$comment->description = $comment_text;
	if ($comment->save()) {
		system_message(elgg_echo('generic_comment:updated'));

		if (elgg_is_xhr()) {
			// @todo move to its own view object/comment/content in 1.x
			echo elgg_view('output/longtext', array(
				'value' => $comment->description,
				'class' => 'elgg-inner',
				'data-role' => 'comment-text',
			));
		}
	} else {
		register_error(elgg_echo('generic_comment:failure'));
	}
} else {
	// Create a new comment on the target entity
	$entity = get_entity($entity_guid);
	if (!$entity) {
		register_error(elgg_echo("generic_comment:notfound"));
		forward(REFERER);
	}

	$user = elgg_get_logged_in_user_entity();

	$comment = new ElggComment();
	$comment->description = $comment_text;
	$comment->owner_guid = $user->getGUID();
	$comment->container_guid = $entity->getGUID();
	$comment->access_id = $entity->access_id;
	$guid = $comment->save();

	if (!$guid) {
		register_error(elgg_echo("generic_comment:failure"));
		forward(REFERER);
	}

	// Add to river
	elgg_create_river_item(array(
		'view' => 'river/object/comment/create',
		'action_type' => 'comment',
		'subject_guid' => $user->guid,
		'object_guid' => $guid,
		'target_guid' => $entity_guid,
	));

	system_message(elgg_echo('generic_comment:posted'));
}

// return to activity page if posted from there
if (!empty($_SERVER['HTTP_REFERER'])) {
	// don't redirect to URLs from client without verifying within site
	$site_url = preg_quote(elgg_get_site_url(), '~');
	if (preg_match("~^{$site_url}activity(/|\\z)~", $_SERVER['HTTP_REFERER'], $m)) {
		forward("{$m[0]}#elgg-object-{$comment->guid}");
	}
}

forward($comment->getURL());
