<?php
declare(strict_types=1);

namespace Enna\Trace;

use Closure;
use Enna\Framework\App;
use Enna\Framework\Config;
use Enna\Framework\Event\LogWrite;
use Enna\Framework\Response;
use Enna\Framework\Response\Redirect;

class TraceDebug
{
    /**
     * 应用
     * @var App
     */
    protected $app;

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * Trace日志
     * @var array
     */
    protected $log = [];

    public function __construct(App $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config->get('trace');
    }

    public function handle($request, Closure $next)
    {
        $debug = $this->app->isDebug();

        //注册日志监听
        if ($debug) {
            $this->log = [];
            $this->app->event->listen(LogWrite::class, function ($event) {
                if (empty($this->config['channel']) || $this->config['channel'] == $event->channel) {
                    $this->log = array_merge_recursive($this->log, $event->log);
                }
            });
        }

        $response = $next($request);

        //注入Trace调试
        if ($debug) {
            $data = $response->getContent();
            $this->traceDebug($response, $data);
            $response->content($data);
        }

        return $response;
    }

    /**
     * Note: 注入Trace调试信息
     * Date: 2023-11-22
     * Time: 10:23
     * @param Response $response
     * @param $content
     */
    public function traceDebug(Response $response, &$content)
    {
        $config = $this->config;
        $type = $config['type'] ?? 'Html';

        unset($config['type']);

        $trace = App::factory($type, '\\Enna\\Trace\\', $config);

        if ($response instanceof Redirect) {

        } else {
            $log = $this->app->log->getLog($config['channel'] ?? '');
            $log = array_merge_recursive($this->log, $log);
            $output = $trace->output($this->app, $response, $log);

            if (is_string($output)) {
                $pos = strripos($content, '</body>');
                if ($pos !== false) {
                    $content = substr($content, 0, $pos) . $output . substr($content, $pos);
                } else {
                    $content = $content . $output;
                }
            }
        }
    }
}