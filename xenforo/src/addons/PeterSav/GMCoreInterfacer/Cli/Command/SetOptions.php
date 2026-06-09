<?php

namespace PeterSav\GModInterface\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;
use XF\Repository\OptionRepository;

class SetOptions extends AbstractCommand
{
	protected function configure()
	{
		$this
			->setName('gmodinterface:set-options')
			->setDescription('Sets XenForo options from the command line.')
			->addOption(
				'file',
				null,
				InputOption::VALUE_REQUIRED,
				'Path to a JSON file with option values.'
			)
			->addOption(
				'env',
				null,
				InputOption::VALUE_NONE,
				'Read option values from environment variables.'
			)
			->addOption(
				'env-prefix',
				null,
				InputOption::VALUE_REQUIRED,
				'Environment variable prefix for options (default: XF_OPTION_).'
			)
			->addOption(
				'option',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Option in key=value format (can be repeated).'
			)
			->addOption(
				'skip-verify',
				null,
				InputOption::VALUE_NONE,
				'Skip option value verification.'
			)
			->addOption(
				'no-rebuild-cache',
				null,
				InputOption::VALUE_NONE,
				'Do not rebuild the option cache.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$values = [];

		$file = $input->getOption('file');
		if ($file) {
			if (!file_exists($file)) {
				$output->writeln('<error>File not found: ' . $file . '</error>');

				return 1;
			}

			$json = file_get_contents($file);
			$data = json_decode($json, true);
			if (!is_array($data)) {
				$output->writeln('<error>Invalid JSON in file: ' . $file . '</error>');

				return 1;
			}

			foreach ($data as $key => $value) {
				$values[$key] = $value;
			}
		}

		if ($input->getOption('env')) {
			$prefix = $input->getOption('env-prefix') ?: 'XF_OPTION_';
			$envVars = getenv();

			if (!is_array($envVars)) {
				$envVars = $_ENV;
			}

			foreach ($envVars as $key => $value) {
				if (strpos($key, $prefix) !== 0) {
					continue;
				}

				$optionKey = substr($key, strlen($prefix));
				if ($optionKey === '') {
					continue;
				}

				$values[$optionKey] = $this->parseValue((string)$value);
			}
		}

		$options = $input->getOption('option') ?? [];
		foreach ($options as $pair) {
			if (strpos($pair, '=') === false) {
				$output->writeln('<error>Invalid option format: ' . $pair . ' (expected key=value)</error>');

				return 1;
			}

			[$key, $rawValue] = explode('=', $pair, 2);
			$key = trim($key);
			$values[$key] = $this->parseValue($rawValue);
		}

		if (!$values) {
			$output->writeln('<error>No options provided. Use --file, --option, or --env.</error>');

			return 1;
		}

		$optionRepo = \XF::repository(OptionRepository::class);
		$skipVerify = (bool)$input->getOption('skip-verify');

		foreach ($values as $key => $value) {
			try {
				if ($skipVerify) {
					$optionRepo->updateOptionSkipVerify($key, $value);
				} else {
					$optionRepo->updateOption($key, $value);
				}
				$output->writeln('<info>Updated option: ' . $key . '</info>');
			} catch (\Throwable $e) {
				$output->writeln('<error>Failed to update ' . $key . ': ' . $e->getMessage() . '</error>');

				return 1;
			}
		}

		if (!$input->getOption('no-rebuild-cache')) {
			$optionRepo->rebuildOptionCache();
			$output->writeln('<info>Option cache rebuilt.</info>');
		}

		return 0;
	}

	protected function parseValue(string $value)
	{
		$trimmed = trim($value);
		if ($trimmed === '') {
			return '';
		}

		$lower = strtolower($trimmed);
		if ($lower === 'true') {
			return true;
		}
		if ($lower === 'false') {
			return false;
		}
		if ($lower === 'null') {
			return null;
		}

		if (is_numeric($trimmed)) {
			return strpos($trimmed, '.') !== false ? (float)$trimmed : (int)$trimmed;
		}

		if (str_starts_with($trimmed, 'json:')) {
			$json = substr($trimmed, 5);
			$decoded = json_decode($json, true);

			if (json_last_error() === JSON_ERROR_NONE) {
				return $decoded;
			}
		}

		return $trimmed;
	}
}
