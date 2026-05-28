<?php

namespace PeterSav\GModInterface\Admin\Controller;

use XF;
use XF\Mvc\ParameterBag;

class Settings extends \XF\Admin\Controller\AbstractController {
	public function actionIndex() {
		$viewParams = array(
			"test" => true
		);

		return $this->view('', 'gmod_admin_index', $viewParams);
	}
}
