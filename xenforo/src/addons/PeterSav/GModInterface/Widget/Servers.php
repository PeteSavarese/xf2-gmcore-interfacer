<?php

namespace PeterSav\GModInterface\Widget;

class Servers extends AbstractWidget {
	public function render() {
		$dbCore = self::getCoreDbInstance();

		if (!$dbCore) {
			// Graceful fail instead of killin the entire forum page
			\XF::logError('GModInterface: core DB unavailable in Servers widget; rendering empty list.');

			return $this->renderer('gmod_servers_widget', [
				'servers' => []
			]);
		}

		try {
			$fetchServers = $dbCore->fetchAll('SELECT * FROM servers');
		} catch (\Throwable $e) {
			\XF::logError('GModInterface: failed to fetch servers: ' . $e->getMessage());
			$fetchServers = [];
		}

		foreach ($fetchServers as $k => $v) {
			$fetchServers[$k]['offline'] = ($v['lastUpdate'] < time() - 300);
		}

		return $this->renderer('gmod_servers_widget', [
			'servers' => $fetchServers
		]);
	}
}
