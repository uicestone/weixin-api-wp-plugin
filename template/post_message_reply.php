<xml>
	<ToUserName><![CDATA[<?=$received_message['FROMUSERNAME']?>]]></ToUserName>
	<FromUserName><![CDATA[<?=$received_message['TOUSERNAME']?>]]></FromUserName>
	<CreateTime><?=time()?></CreateTime>
	<MsgType><![CDATA[news]]></MsgType>
	<ArticleCount><?=$reply_posts_count?></ArticleCount>
	<Articles>
		<?php foreach($reply_posts as $reply_post){ ?>
		<item>
			<Title><![CDATA[<?=$reply_post->post_title?>]]></Title> 
			<Description><![CDATA[<?=$reply_post->post_excerpt?>]]></Description>
			<PicUrl><![CDATA[<?=wp_get_attachment_url(get_post_thumbnail_id($reply_post->ID))?>]]></PicUrl>
			<Url><![CDATA[<?=get_permalink($reply_post->ID)?>]]></Url>
		</item>
		<?php } ?>
	</Articles>
</xml> 