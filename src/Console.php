<?php
declare(strict_types=1);

namespace Enna\Trace;

use Enna\Framework\App;
use Enna\Framework\Response;

class Console
{
    protected $config = [
        'tabs' => [
            'base' => '基本',
            'file' => '文件',
            'info' => '流程',
            'notice|error' => '错误',
            'sql' => 'SQL',
            'debug|log' => '调试'
        ],
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    public function output(App $app, Response $response, array $log = [])
    {
        $request = $app->request;
        $contentType = $response->getHeader('Content-Type');

        if ($request->isJson() || $request->isAjax()) {
            return false;
        } elseif (!empty($contentType) && strpos($contentType, 'html') === false) {
            return false;
        } elseif ($response->getCode() == 204) {
            return false;
        }

        //获取基本信息
        $runtime = number_format(microtime(true) - $app->getBeginTime(), 10, '.', '');
        $reqs = $runtime > 0 ? number_format(1 / $runtime, 2) : '∞';
        $mem = number_format(memory_get_usage() - $app->getBeginMem() / 1024, 2);

        if ($request->host()) {
            $uri = $request->protocol() . ' ' . $request->method() . ' : ' . $request->url(true);
        } else {
            $uri = 'cmd:' . implode(' ', $_SERVER['argv']);
        }

        //基本
        $base = [
            '请求信息' => date('Y-m-d H:i:s', $request->time() ?: time()) . ' ' . $uri,
            '运行信息' => number_format((float)$runtime, 6) . 's [吞吐率:' . $reqs . 'req/s ] 内存消耗:' . $mem . 'kb 文件加载:' . count(get_included_files()),
            '查询信息' => $app->db->getQueryTimes() . ' queries',
            '缓存信息' => $app->cache->getReadTimes() . ' reads,' . $app->cache->getWriteTimes() . ' writes',
        ];
        if (isset($app->session)) {
            $base['会话信息'] = 'SESSION_ID=' . $app->session->getSessionId();
        }

        //文件
        $file = $this->getFileInfo();

        //trace信息
        $trace = [];
        foreach ($this->config['tabs'] as $name => $title) {
            $name = strtolower($name);
            switch ($name) {
                case 'base': //基本信息
                    $trace[$title] = $base;
                    break;
                case 'file': //文件信息
                    $trace[$title] = $file;
                    break;
                default: //调试信息
                    if (strpos($name, '|')) {
                        $names = explode('|', $name);
                        $result = [];
                        foreach ($names as $item) {
                            $result = array_merge($result, $log[$item] ?? []);
                        }
                        $trace[$title] = $result;
                    } else {
                        $trace[$title] = $log[$name] ?? '';
                    }
            }
        }

        //输出到控制台
        $lines = '';
        foreach ($trace as $type => $msg) {
            $lines .= $this->console($type, empty($msg) ? [] : $msg);
        }

        $js = <<<JS
        <script type='text/javascript'>
        {$lines}
        </script>
        JS;
        return $js;
    }

    protected function console(string $type, $msg)
    {
        $type = strtolower($type);
        $trace_tabs = array_values($this->config['tabs']);
        $line = [];
        $line[] = ($type == $trace_tabs[0] || '调试' == $type || '错误' == $type)
            ? "console.group('{$type}');"
            : "console.groupCollapsed('{$type}');";

        foreach ((array)$msg as $key => $m) {
            switch ($type) {
                case '调试':
                    $var_type = gettype($m);
                    if (in_array($var_type, ['array', 'string'])) {
                        $line[] = "console.log(" . json_encode($m) . ");";
                    } else {
                        $line[] = "console.log(" . json_encode(var_export($m, true)) . ");";
                    }
                    break;
                case '错误':
                    $msg = str_replace("\n", '\n', addslashes(is_scalar($m) ? $m : json_encode($m)));
                    $style = 'color:#F4006B;font-size:14px;';
                    $line[] = "console.error(\"%c{$msg}\", \"{$style}\");";
                    break;
                case 'sql':
                    $msg = str_replace("\n", '\n', addslashes($m));
                    $style = "color:#009bb4;";
                    $line[] = "console.log(\"%c{$msg}\", \"{$style}\");";
                    break;
                default:
                    $m = is_string($key) ? $key . ' ' . $m : $key + 1 . ' ' . $m;
                    $msg = json_encode($m);
                    $line[] = "console.log({$msg});";
                    break;
            }
        }
        $line[] = "console.groupEnd();";
        return implode(PHP_EOL, $line);
    }

    /**
     * Note: 获取文件加载信息
     * Date: 2023-11-22
     * Time: 15:02
     * @return array
     */
    protected function getFileInfo()
    {
        $files = get_included_files();
        $info = [];

        foreach ($files as $key => $file) {
            $info[] = $file . ' ( ' . number_format(filesize($file) / 1024, 2) . ' KB )';
        }

        return $info;
    }
}