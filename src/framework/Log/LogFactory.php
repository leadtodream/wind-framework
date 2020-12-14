<?php

namespace Framework\Log;

use Framework\Base\Config;
use Monolog\Logger;

class LogFactory
{

    private $loggers = [];

    public function get($name='app', $group='default')
    {
        $key = $name.':'.$group;

        if (isset($this->loggers[$key])) {
            return $this->loggers[$key];
        }

        $config = di()->get(Config::class);

        $setting = $config->get('log.'.$group);

        if (empty($setting)) {
            throw new \Exception("Logger group '$group' not found in config.");
        }

        // create a log channel
        $log = new Logger($name);

        //支持多 Handlers 或单 Handler 配置
        if (!isset($setting['handlers'])) {
            $setting['handlers'] = [];
        }

        if (isset($setting['handler'])) {
            $setting['handlers'][] = $setting['handler'];
        }

        if (empty($setting['handlers'])) {
            throw new \Exception("No handlers config for logger group '$group'!");
        }

        foreach ($setting['handlers'] as $h) {
            if (!empty($h['async']) && !defined('TASK_WORKER')) {
                $level = $h['args']['level'] ?? Logger::DEBUG;
                $bubble = $h['args']['bubble'] ?? true;
                $handler = new AsyncHandler($level, $bubble);
                $handler->setGroup($group);
            } else {
                $args = $h['args'] ?? [];
                $handler = di()->make($h['class'], $args);
            }

            $fmt = $h['formatter'] ?? $setting['formatter'] ?? false;
            if ($fmt) {
                $formatter = di()->make($fmt['class'], $fmt['args'] ?? []);
                $handler->setFormatter($formatter);
            }

            $log->pushHandler($handler);
        }

        return $this->loggers[$key] = $log;
    }

}