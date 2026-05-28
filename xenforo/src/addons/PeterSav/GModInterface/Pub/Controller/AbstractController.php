<?php

namespace PeterSav\GModInterface\Pub\Controller;

use XF\Pub\Controller\AbstractController as XFAbstractController;

abstract class AbstractController extends XFAbstractController {
	public function __construct(\XF\App $app, \XF\Http\Request $request) {
		parent::__construct($app, $request);
	}

	public static function getCoreDbInstance() {
		return \PeterSav\GModInterface\Helper\CoreDatabase::getCoreDbInstance();
	}
}
