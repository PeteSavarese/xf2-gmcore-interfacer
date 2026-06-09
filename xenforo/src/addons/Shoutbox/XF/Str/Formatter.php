<?php

namespace Shoutbox\XF\Str;

class Formatter extends XFCP_Formatter
{
	public function replaceSmiliesInText($text, $replaceCallback, $escapeCallback = null)
	{
		if (!$this->smilieTranslate)
		{
			return parent::replaceSmiliesInText($text, $replaceCallback, $escapeCallback);
		}

		$protected = [];
		$smilieTextSet = array_flip(array_keys($this->smilieTranslate));

		$text = preg_replace_callback(
			'/:[a-z0-9_+\\-]+:/i',
			function ($match) use (&$protected, $smilieTextSet)
			{
				if (isset($smilieTextSet[$match[0]]))
				{
					return $match[0];
				}
				$token = "\x01SBX" . count($protected) . "\x01";
				$protected[$token] = $match[0];
				return $token;
			},
			$text
		);

		$result = parent::replaceSmiliesInText($text, $replaceCallback, $escapeCallback);

		if ($protected)
		{
			$result = strtr($result, $protected);
		}

		return $result;
	}
}
