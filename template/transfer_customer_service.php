<xml>
	<ToUserName><![CDATA[<?=$received_message['FROMUSERNAME']?>]]></ToUserName>
	<FromUserName><![CDATA[<?=$received_message['TOUSERNAME']?>]]></FromUserName>
	<CreateTime><?=time()?></CreateTime>
	<MsgType><![CDATA[transfer_customer_service]]></MsgType>
</xml>