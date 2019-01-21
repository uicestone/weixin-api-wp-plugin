<xml>
	<ToUserName><![CDATA[<?=$received_message['FROMUSERNAME']?>]]></ToUserName>
	<FromUserName><![CDATA[<?=$received_message['TOUSERNAME']?>]]></FromUserName>
	<CreateTime><![CDATA[<?=time()?>]]></CreateTime>
	<MsgType><![CDATA[text]]></MsgType>
	<Content><![CDATA[<?=$reply_message_content?>]]></Content>
	<FuncFlag><![CDATA[0]]></FuncFlag>
</xml>
