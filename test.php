<?php
// 这是环境检测文件，用于全面评估服务器是否支持网站运行
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__.'/include/Db.php';
$requirements = [
    'php_version' => '7.4.0',
    'hardware' => [
        'memory' => ['min' => '1.5GB', 'desc' => '内存要求（至少1.5GB）'],
        'cpu_cores' => ['min' => 2, 'desc' => '逻辑CPU核心数（至少2核）'],
        'disk_space' => ['min' => '100MB', 'desc' => '磁盘空间（根据使用量自行决定，至少100MB）']
    ],
    'extensions' => [
        'mbstring' => '处理多字节字符（必需）',
        'json' => 'JSON数据处理（必需）',
        'session' => '用户会话管理（必需）',
        'curl' => '网络请求处理（推荐）',
        'fileinfo' => '文件类型检测（推荐）',
        'gd' => '图片处理（可选）',
        'zip' => '压缩解压（用于更新，推荐）',
        'mysqli' => 'MySQL数据库连接（必需）'
    ],
    'directories' => [
        'cache' => ['path' => 'cache', 'desc' => '缓存主目录（必需读写）'],
        'cache_data' => ['path' => 'cache/data', 'desc' => '缓存数据目录（必需读写）'],
        'cache_comments' => ['path' => 'cache/comments', 'desc' => '评论缓存目录（必需读写）'],
        'admin' => ['path' => 'admin', 'desc' => '管理后台目录（必需可读）']
    ],
    'php_settings' => [
        'memory_limit' => ['min' => '64M', 'desc' => 'PHP内存限制（推荐至少64M）'],
        'max_execution_time' => ['min' => 30, 'desc' => '最大执行时间（推荐至少30秒）'],
        'upload_max_filesize' => ['min' => '2M', 'desc' => '文件上传限制（推荐至少2M）']
    ],
    'database' => [
        'type' => 'MySQL/MariaDB',
        'version' => '5.6.0',
        'charset' => 'utf8mb4'
    ]
];
$results = [
    'environment' => [],
    'hardware' => [],
    'extensions' => [],
    'directories' => [],
    'php_settings' => [],
    'functions' => [],
    'cache' => [],
    'database' => []
];
function get_hardware_info() {
    $info = [
        'os' => '未知',
        'cpu_model' => '未知',
        'cpu_cores' => '未知',
        'cpu_physical_cores' => '未知',
        'memory_total' => '未知',
        'memory_available' => '未知',
        'disk_total' => '未知',
        'disk_free' => '未知'
    ];
    $info['os'] = php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m');
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $info = get_windows_hardware_info($info);
    } elseif (PHP_OS === 'Darwin') {
        $info = get_macos_hardware_info($info);
    } else {
        $info = get_linux_hardware_info($info);
    }
    return $info;
}
function get_windows_hardware_info($info) {
    if (function_exists('shell_exec') && is_callable('shell_exec')) {
        try {
            $cpuinfo = shell_exec('wmic cpu get name /value 2>nul');
            if ($cpuinfo && preg_match('/Name=([^\r\n]+)/', $cpuinfo, $matches)) {
                $info['cpu_model'] = trim($matches[1]);
            }
            $cpucores = shell_exec('wmic cpu get NumberOfCores,NumberOfLogicalProcessors /value 2>nul');
            if ($cpucores) {
                if (preg_match('/NumberOfCores=(\d+)/', $cpucores, $matches)) {
                    $info['cpu_physical_cores'] = intval($matches[1]);
                }
                if (preg_match('/NumberOfLogicalProcessors=(\d+)/', $cpucores, $matches)) {
                    $info['cpu_cores'] = intval($matches[1]);
                }
            }
            $memory = shell_exec('wmic ComputerSystem get TotalPhysicalMemory /value 2>nul');
            if ($memory && preg_match('/TotalPhysicalMemory=(\d+)/', $memory, $matches)) {
                $info['memory_total'] = format_bytes($matches[1]);
            }
        } catch (Exception $e) {
        }
    }
    $disk_total = disk_total_space(".");
    $disk_free = disk_free_space(".");
    if ($disk_total !== false) {
        $info['disk_total'] = format_bytes($disk_total);
    }
    if ($disk_free !== false) {
        $info['disk_free'] = format_bytes($disk_free);
    }
    return $info;
}
function get_macos_hardware_info($info) {
    if (function_exists('shell_exec') && is_callable('shell_exec')) {
        try {
            $cpuinfo = shell_exec('sysctl -n machdep.cpu.brand_string 2>/dev/null');
            if ($cpuinfo) {
                $info['cpu_model'] = trim($cpuinfo);
            }
            $physical_cores = shell_exec('sysctl -n hw.physicalcpu 2>/dev/null');
            $logical_cores = shell_exec('sysctl -n hw.logicalcpu 2>/dev/null');
            if ($physical_cores) {
                $info['cpu_physical_cores'] = intval(trim($physical_cores));
            }
            if ($logical_cores) {
                $info['cpu_cores'] = intval(trim($logical_cores));
            }
            $memory = shell_exec('sysctl -n hw.memsize 2>/dev/null');
            if ($memory) {
                $info['memory_total'] = format_bytes(trim($memory));
            }
        } catch (Exception $e) {
        }
    }
    $disk_total = disk_total_space(".");
    $disk_free = disk_free_space(".");
    if ($disk_total !== false) {
        $info['disk_total'] = format_bytes($disk_total);
    }
    if ($disk_free !== false) {
        $info['disk_free'] = format_bytes($disk_free);
    }
    return $info;
}
function get_linux_hardware_info($info) {
    $canAccessProc = true;
    $openBasedir = ini_get('open_basedir');
    if (!empty($openBasedir)) {
        $allowedPaths = explode(PATH_SEPARATOR, $openBasedir);
        $canAccessProc = false;
        foreach ($allowedPaths as $path) {
            if (strpos('/proc', $path) === 0 || $path === '/proc' || $path === '/proc/') {
                $canAccessProc = true;
                break;
            }
        }
    }
    if ($canAccessProc && file_exists('/proc/cpuinfo')) {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        if ($cpuinfo) {
            if (preg_match('/model name\s*:\s*([^\n]+)/', $cpuinfo, $matches)) {
                $info['cpu_model'] = trim($matches[1]);
            } else {
                $info['cpu_model'] = '无法识别型号';
            }
            $physical_ids = [];
            $core_ids = [];
            $lines = explode("\n", $cpuinfo);
            foreach ($lines as $line) {
                if (preg_match('/physical id\s*:\s*(\d+)/', $line, $matches)) {
                    $physical_ids[$matches[1]] = true;
                }
                if (preg_match('/core id\s*:\s*(\d+)/', $line, $matches)) {
                    $core_ids[$matches[1]] = true;
                }
            }
            $info['cpu_physical_cores'] = count($core_ids) > 0 ? count($core_ids) : '无法识别';
            $info['cpu_cores'] = substr_count($cpuinfo, 'processor') > 0 ? substr_count($cpuinfo, 'processor') : '无法识别';
        } else {
            $info['cpu_model'] = '文件读取失败';
            $info['cpu_physical_cores'] = '文件读取失败';
            $info['cpu_cores'] = '文件读取失败';
        }
    } else {
        $info['cpu_model'] = '无权限识别';
        $info['cpu_physical_cores'] = '无权限识别';
        $info['cpu_cores'] = '无权限识别';
    }
    if ($canAccessProc && file_exists('/proc/meminfo')) {
        $meminfo = file_get_contents('/proc/meminfo');
        if ($meminfo && preg_match('/MemTotal:\s*(\d+)\s*kB/', $meminfo, $matches)) {
            $info['memory_total'] = format_bytes($matches[1] * 1024);
        } else {
            $info['memory_total'] = '无法识别';
        }
    } else {
        $info['memory_total'] = '无权限识别';
    }
    $disk_total = disk_total_space(".");
    $disk_free = disk_free_space(".");
    if ($disk_total !== false) {
        $info['disk_total'] = format_bytes($disk_total);
    } else {
        $info['disk_total'] = '无法识别';
    }
    if ($disk_free !== false) {
        $info['disk_free'] = format_bytes($disk_free);
    } else {
        $info['disk_free'] = '无法识别';
    }
    return $info;
}
function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
function check_database_config() {
    $dbConfigPath = __DIR__ . '/include/Db.php';
    $results = [];
    $dbConfig = [];
    if (!file_exists($dbConfigPath)) {
        $results[] = [
            'name' => '数据库配置文件',
            'status' => 'error',
            'value' => '未找到配置文件',
            'suggestion' => '请确保 ' . $dbConfigPath . ' 文件存在'
        ];
        return ['results' => $results, 'config' => $dbConfig];
    }
    $configContent = file_get_contents($dbConfigPath);
    $host = $db = $user = $pass = $charset = null;
    if (preg_match('/private\s*\$host\s*=\s*\'([^\']*)\';/', $configContent, $matches)) {
        $host = $matches[1];
        $status = !empty($host) && $host !== '主机地址' ? 'success' : 'error';
        $results[] = [
            'name' => '数据库主机',
            'status' => $status,
            'value' => $status === 'success' ? '配置正常' : '配置错误',
            'suggestion' => $status === 'error' ? '请正确配置数据库主机地址' : ''
        ];
    } else {
        $results[] = [
            'name' => '数据库主机',
            'status' => 'warning',
            'value' => '未找到配置',
            'suggestion' => '建议在 Db.php 中以 private $host = \'...\' 的形式配置主机地址'
        ];
    }
    if (preg_match('/private\s*\$db\s*=\s*\'([^\']*)\';/', $configContent, $matches)) {
        $db = $matches[1];
        $status = !empty($db) && $db !== '数据库名' ? 'success' : 'error';
        $results[] = [
            'name' => '数据库名',
            'status' => $status,
            'value' => $status === 'success' ? '配置正常' : '配置错误',
            'suggestion' => $status === 'error' ? '请正确配置数据库名' : ''
        ];
    } else {
        $results[] = [
            'name' => '数据库名',
            'status' => 'warning',
            'value' => '未找到配置',
            'suggestion' => '建议在 Db.php 中以 private $db = \'...\' 的形式配置数据库名'
        ];
    }
    if (preg_match('/private\s*\$user\s*=\s*\'([^\']*)\';/', $configContent, $matches)) {
        $user = $matches[1];
        $status = !empty($user) && $user !== '数据库用户名' ? 'success' : 'error';
        $results[] = [
            'name' => '数据库用户名',
            'status' => $status,
            'value' => $status === 'success' ? '配置正常' : '配置错误',
            'suggestion' => $status === 'error' ? '请正确配置数据库用户名' : ''
        ];
    } else {
        $results[] = [
            'name' => '数据库用户名',
            'status' => 'warning',
            'value' => '未找到配置',
            'suggestion' => '建议在 Db.php 中以 private $user = \'...\' 的形式配置用户名'
        ];
    }
    if (preg_match('/private\s*\$pass\s*=\s*\'([^\']*)\';/', $configContent, $matches)) {
        $pass = $matches[1];
        $status = ($pass !== '密码' && $pass !== null) ? 'success' : 'error';
        $results[] = [
            'name' => '数据库密码',
            'status' => $status,
            'value' => $status === 'success' ? '已配置' : '配置错误',
            'suggestion' => $status === 'error' ? '请正确配置数据库密码' : ($pass === '' ? '生产环境请设置强密码' : '')
        ];
    } else {
        $results[] = [
            'name' => '数据库密码',
            'status' => 'warning',
            'value' => '未找到配置',
            'suggestion' => '建议在 Db.php 中以 private $pass = \'...\' 的形式配置密码'
        ];
    }
    if (preg_match('/private\s*\$charset\s*=\s*\'([^\']*)\';/', $configContent, $matches)) {
        $charset = $matches[1];
        $status = $charset === 'utf8mb4' ? 'success' : 'warning';
        $results[] = [
            'name' => '数据库字符集',
            'status' => $status,
            'value' => $charset ?: '未设置',
            'suggestion' => $status === 'success' ? '推荐字符集配置正确' : '建议使用 utf8mb4 字符集以支持更多 Unicode 字符'
        ];
    } else {
        $results[] = [
            'name' => '数据库字符集',
            'status' => 'warning',
            'value' => '未找到配置',
            'suggestion' => '建议在 Db.php 中配置 charset 参数为 utf8mb4'
        ];
    }
    $dbConfig = [
        'host' => $host,
        'db' => $db,
        'user' => $user,
        'pass' => $pass,
        'charset' => $charset
    ];
    return ['results' => $results, 'config' => $dbConfig];
}
function test_database_connection($config) {
    $result = [
        'name' => '数据库连接测试',
        'status' => 'error',
        'value' => '连接失败',
        'suggestion' => '请检查数据库配置是否正确'
    ];
    if (empty($config['host']) || empty($config['db']) || empty($config['user'])) {
        $result['value'] = '缺少必要配置参数';
        return $result;
    }
    $conn = @new mysqli($config['host'], $config['user'], $config['pass'], $config['db']);
    if ($conn->connect_error) {
        $errorMsg = match ($conn->connect_errno) {
            1045 => '用户名或密码错误',
            1049 => '数据库不存在',
            2002 => '无法连接到数据库主机',
            default => '连接错误 (#' . $conn->connect_errno . ')'
        };
        $result['value'] = '连接失败: ' . $errorMsg;
        return $result;
    }
    $serverVersion = $conn->server_info;
    $requiredVersion = '5.6.0';
    if (version_compare($serverVersion, $requiredVersion, '<')) {
        $result['status'] = 'warning';
        $result['value'] = "连接成功，但数据库版本过低 (当前: $serverVersion，要求: $requiredVersion)";
        $result['suggestion'] = '建议升级数据库至' . $requiredVersion . '或更高版本';
        $conn->close();
        return $result;
    }
    if (!empty($config['charset']) && $config['charset'] === 'utf8mb4') {
        if (!$conn->set_charset($config['charset'])) {
            $result['status'] = 'warning';
            $result['value'] = '连接成功，但字符集设置失败';
            $result['suggestion'] = '请确保数据库支持utf8mb4字符集';
            $conn->close();
            return $result;
        }
    }
    $conn->close();
    $result['status'] = 'success';
    $result['value'] = '连接成功';
    $result['suggestion'] = '';  
    return $result;
}
$results['environment'][] = [
    'name' => 'PHP 运行状态',
    'status' => 'success',
    'value' => '正常运行',
    'suggestion' => ''
];
$results['environment'][] = [
    'name' => '服务器软件',
    'status' => 'info',
    'value' => $_SERVER['SERVER_SOFTWARE'] ?? '未知',
    'suggestion' => ''
];
$phpVersionCurrent = PHP_VERSION;
$phpVersionCheck = version_compare($phpVersionCurrent, $requirements['php_version'], '>=');
$results['environment'][] = [
    'name' => 'PHP 版本',
    'status' => $phpVersionCheck ? 'success' : 'error',
    'value' => $phpVersionCurrent . '（要求 ≥ ' . $requirements['php_version'] . '）',
    'suggestion' => $phpVersionCheck ? '' : '请升级PHP版本至' . $requirements['php_version'] . '或更高'
];
$hardwareInfo = get_hardware_info();
$results['hardware'][] = [
    'name' => '操作系统',
    'status' => 'info',
    'value' => $hardwareInfo['os'],
    'suggestion' => ''
];
$results['hardware'][] = [
    'name' => 'CPU 型号',
    'status' => 'info',
    'value' => $hardwareInfo['cpu_model'],
    'suggestion' => '推荐：Intel Xeon E5-2673 v4 或更高性能CPU'
];
$cpuPhysicalCores = $hardwareInfo['cpu_physical_cores'];
$minPhysicalCores = 1;
if ($cpuPhysicalCores === '未知' || $cpuPhysicalCores === '无权限识别') {
    $status = 'warning';
    $value = $cpuPhysicalCores;
    $suggestion = '由于服务器安全限制（open_basedir），无法获取CPU信息。请联系管理员开放/proc目录访问权限，建议至少' . $minPhysicalCores . '个物理CPU';
} else {
    $coreCheck = $cpuPhysicalCores >= $minPhysicalCores;
    $status = $coreCheck ? 'success' : 'error';
    $value = $cpuPhysicalCores . ' 核心（要求 ≥ ' . $minPhysicalCores . ' 核心）';
    $suggestion = $coreCheck ? '' : '物理CPU核心数不足，建议至少' . $minPhysicalCores . '个物理CPU';
}
$results['hardware'][] = [
    'name' => '物理CPU核心',
    'status' => $status,
    'value' => $value,
    'suggestion' => $suggestion
];
$cpuCores = $hardwareInfo['cpu_cores'];
if ($cpuCores === '无权限识别') {
    $results['hardware'][] = [
        'name' => '逻辑CPU核心',
        'status' => 'warning',
        'value' => $cpuCores,
        'suggestion' => '由于服务器安全限制（open_basedir），无法获取CPU信息。请联系管理员开放/proc目录访问权限，建议至少' . $requirements['hardware']['cpu_cores']['min'] . '个逻辑核心'
    ];
} else {
    $cpuCoresCheck = ($cpuCores !== '未知' && $cpuCores >= $requirements['hardware']['cpu_cores']['min']);
    $results['hardware'][] = [
        'name' => '逻辑CPU核心',
        'status' => $cpuCoresCheck ? 'success' : ($cpuCores === '未知' ? 'warning' : 'error'),
        'value' => $cpuCores !== '未知' ? $cpuCores . ' 核心（要求 ≥ ' . $requirements['hardware']['cpu_cores']['min'] . ' 核心）' : '未知',
        'suggestion' => $cpuCoresCheck ? '' : 'CPU核心数不足，建议升级到至少' . $requirements['hardware']['cpu_cores']['min'] . '个逻辑核心'
    ];
}
$memoryTotal = $hardwareInfo['memory_total'];
if ($memoryTotal !== '未知' && $memoryTotal !== '无权限识别') {
    $memoryBytes = return_bytes($memoryTotal);
    $requiredMemoryBytes = return_bytes($requirements['hardware']['memory']['min']);
    $memoryCheck = $memoryBytes >= $requiredMemoryBytes;
    $results['hardware'][] = [
        'name' => '系统总内存',
        'status' => $memoryCheck ? 'success' : 'error',
        'value' => $memoryTotal . '（要求 ≥ ' . $requirements['hardware']['memory']['min'] . '）',
        'suggestion' => $memoryCheck ? '' : '内存不足，建议升级到至少' . $requirements['hardware']['memory']['min']
    ];
} else if ($memoryTotal === '无权限识别') {
    $results['hardware'][] = [
        'name' => '系统总内存',
        'status' => 'warning',
        'value' => $memoryTotal,
        'suggestion' => '由于服务器安全限制（open_basedir），无法获取内存信息。请联系管理员开放/proc目录访问权限，建议至少' . $requirements['hardware']['memory']['min'] . '内存'
    ];
} else {
    $results['hardware'][] = [
        'name' => '系统总内存',
        'status' => 'warning',
        'value' => '未知',
        'suggestion' => '无法检测内存信息，请确保系统内存至少' . $requirements['hardware']['memory']['min']
    ];
}
if ($hardwareInfo['disk_total'] !== '未知') {
    $results['hardware'][] = [
        'name' => '磁盘总空间',
        'status' => 'info',
        'value' => $hardwareInfo['disk_total'],
        'suggestion' => '根据使用量自行决定，建议预留充足空间'
    ];
    if ($hardwareInfo['disk_free'] !== '未知') {
        $results['hardware'][] = [
            'name' => '磁盘可用空间',
            'status' => 'info',
            'value' => $hardwareInfo['disk_free'],
            'suggestion' => '确保有足够空间存储缓存文件'
        ];
    }
} else {
    $results['hardware'][] = [
        'name' => '磁盘空间',
        'status' => 'warning',
        'value' => '未知',
        'suggestion' => '无法检测磁盘信息，请确保有足够磁盘空间'
    ];
}
foreach ($requirements['extensions'] as $ext => $desc) {
    $loaded = extension_loaded($ext);
    $results['extensions'][] = [
        'name' => ucfirst($ext) . ' 扩展',
        'status' => $loaded ? 'success' : (strpos($desc, '必需') !== false ? 'error' : 'warning'),
        'value' => $loaded ? '已加载' : '未加载',
        'suggestion' => $loaded ? '' : '请安装并启用' . ucfirst($ext) . '扩展：' . $desc
    ];
}
foreach ($requirements['directories'] as $dir => $info) {
    $path = $info['path'];
    $exists = file_exists($path);
    $readable = $exists ? is_readable($path) : false;
    $writable = $exists ? is_writable($path) : false;
    if (!$exists) {
        $status = 'error';
        $value = '目录不存在';
        $suggestion = '请创建目录: ' . $path;
    } elseif (!$readable) {
        $status = 'error';
        $value = '存在但不可读';
        $suggestion = '请设置目录可读权限: ' . $path;
    } elseif (strpos($info['desc'], '必需读写') !== false && !$writable) {
        $status = 'error';
        $value = '存在但不可写';
        $suggestion = '请设置目录可写权限: ' . $path . ' (推荐权限0755)';
    } else {
        $status = 'success';
        $value = '存在且权限正常';
        $suggestion = '';
    }
    $results['directories'][] = [
        'name' => $info['desc'],
        'status' => $status,
        'value' => $value . ' (' . $path . ')',
        'suggestion' => $suggestion
    ];
}
foreach ($requirements['php_settings'] as $setting => $info) {
    $current = ini_get($setting);
    if (in_array($setting, ['memory_limit', 'upload_max_filesize'])) {
        $currentVal = return_bytes($current);
        $minVal = return_bytes($info['min']);
        $status = $currentVal >= $minVal ? 'success' : 'warning';
        $value = $current . '（要求 ≥ ' . $info['min'] . '）';
    } 
    else {
        $currentVal = intval($current);
        $minVal = $info['min'];
        $status = $currentVal >= $minVal ? 'success' : 'warning';
        $value = $current . '秒（要求 ≥ ' . $minVal . '秒）';
    }
    $results['php_settings'][] = [
        'name' => ucwords(str_replace('_', ' ', $setting)),
        'status' => $status,
        'value' => $value,
        'suggestion' => $status == 'warning' ? '请在php.ini中调整' . $setting . '至' . $info['min'] . '或更高' : ''
    ];
}
$requiredFunctions = [
    'random_bytes' => '加密功能（下载链接加密必需）',
    'preg_replace_callback' => '短代码解析（必需）',
    'file_put_contents' => '文件写入（缓存和配置必需）',
    'session_start' => '会话管理（管理员登录必需）'
];
foreach ($requiredFunctions as $func => $desc) {
    $exists = function_exists($func);
    $results['functions'][] = [
        'name' => $func . '() 函数',
        'status' => $exists ? 'success' : 'error',
        'value' => $exists ? '支持' : '不支持',
        'suggestion' => $exists ? '' : $desc . '，请确保PHP配置支持该函数'
    ];
}
$dbCheckResult = check_database_config();
$dbConfigResults = $dbCheckResult['results'];
$dbConfig = $dbCheckResult['config'];
foreach ($dbConfigResults as $dbResult) {
    $results['database'][] = $dbResult;
}
if (extension_loaded('mysqli')) {
    $connectionTestResult = test_database_connection($dbConfig);
    $results['database'][] = $connectionTestResult;
    $mysqliVersion = mysqli_get_client_info();
    $results['database'][] = [
        'name' => 'MySQL客户端版本',
        'status' => 'info',
        'value' => $mysqliVersion,
        'suggestion' => ''
    ];
} else {
    $results['database'][] = [
        'name' => '数据库连接测试',
        'status' => 'error',
        'value' => '无法测试',
        'suggestion' => '请先安装并启用mysqli扩展'
    ];
}
if (extension_loaded('pdo_mysql')) {
    $pdoDrivers = PDO::getAvailableDrivers();
    if (in_array('mysql', $pdoDrivers)) {
        $results['database'][] = [
            'name' => 'PDO MySQL驱动',
            'status' => 'success',
            'value' => '已启用',
            'suggestion' => ''
        ];
    }
}
$cacheTestResult = '未测试';
$cacheStatus = 'info';
$cacheSuggestion = '';
try {
    if (file_exists('cache/FileCache.php')) {
        require_once 'cache/FileCache.php';
        $cache = new FileCache();
        $testKey = 'environment_test_' . uniqid();
        $testValue = 'test_data_' . time();
        $cache->set($testKey, $testValue, 10);
        $retrieved = $cache->get($testKey);
        if ($retrieved === $testValue) {
            $cacheTestResult = '缓存读写正常';
            $cacheStatus = 'success';
        } else {
            $cacheTestResult = '缓存读写失败';
            $cacheStatus = 'error';
            $cacheSuggestion = '请检查cache目录权限或FileCache.php是否正常';
        }
        $cache->delete($testKey);
    } else {
        $cacheTestResult = 'FileCache.php文件不存在';
        $cacheStatus = 'warning';
        $cacheSuggestion = '请确保cache目录下存在FileCache.php文件';
    }
} catch (Exception $e) {
    $cacheTestResult = '缓存测试抛出异常: ' . $e->getMessage();
    $cacheStatus = 'error';
    $cacheSuggestion = '请检查缓存类实现或服务器环境';
}
$results['cache'][] = [
    'name' => '缓存功能测试',
    'status' => $cacheStatus,
    'value' => $cacheTestResult,
    'suggestion' => $cacheSuggestion
];
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = substr($val, 0, -1);
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}
$hasError = false;
foreach ($results as $category) {
    foreach ($category as $item) {
        if ($item['status'] === 'error') {
            $hasError = true;
            break 2;
        }
    }
}
$overallStatus = $hasError ? 'error' : 'success';
$overallMessage = $hasError ? '服务器环境存在问题，需要修复后才能正常运行' : '服务器环境满足要求，可以正常运行';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服务器环境检测 - YuSoLAB</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .summary {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: bold;
        }
        .summary.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .summary.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .category {
            margin-bottom: 30px;
        }
        .category h2 {
            color: #444;
            font-size: 1.4em;
            margin-bottom: 15px;
            padding-left: 10px;
            border-left: 4px solid #007bff;
        }
        .check-list {
            list-style: none;
            padding: 0;
        }
        .check-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 4px;
            border: 1px solid #eee;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
        }
        .check-item.success {
            border-left: 4px solid #28a745;
            background-color: #f0fff4;
        }
        .check-item.warning {
            border-left: 4px solid #ffc107;
            background-color: #fffcf0;
        }
        .check-item.error {
            border-left: 4px solid #dc3545;
            background-color: #fff5f5;
        }
        .check-item.info {
            border-left: 4px solid #17a2b8;
            background-color: #f0f7ff;
        }
        .check-name {
            flex: 0 0 250px;
            font-weight: bold;
        }
        .check-value {
            flex: 1;
            margin-bottom: 5px;
        }
        .check-suggestion {
            flex: 100%;
            color: #666;
            font-size: 0.9em;
            padding-top: 5px;
            border-top: 1px dashed #eee;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            color: white;
            margin-left: 10px;
        }
        .status-badge.success {
            background-color: #28a745;
        }
        .status-badge.warning {
            background-color: #ffc107;
        }
        .status-badge.error {
            background-color: #dc3545;
        }
        .status-badge.info {
            background-color: #17a2b8;
        }
        .suggestions-section {
            margin-top: 40px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }
        .suggestions-section h2 {
            color: #333;
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>KiraUI 环境检测</h1>
        <div class="summary <?php echo $overallStatus; ?>">
            总体状态: <?php echo $overallMessage; ?>
        </div>
        <div class="category">
            <h2>基础环境信息</h2>
            <ul class="check-list">
                <?php foreach ($results['environment'] as $item): ?>
                <li class="check-item <?php echo $item['status']; ?>">
                    <div class="check-name"><?php echo $item['name']; ?></div>
                    <div class="check-value">
                        <?php echo $item['value']; ?>
                        <span class="status-badge <?php echo $item['status']; ?>">
                            <?php echo $item['status'] === 'success' ? '通过' : ($item['status'] === 'error' ? '错误' : '信息'); ?>
                        </span>
                    </div>
                    <?php if ($item['suggestion']): ?>
                    <div class="check-suggestion">建议: <?php echo $item['suggestion']; ?></div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="category">
            <h2>服务器硬件信息</h2>
            <ul class="check-list">
                <?php foreach ($results['hardware'] as $item): ?>
                <li class="check-item <?php echo $item['status']; ?>">
                    <div class="check-name"><?php echo $item['name']; ?></div>
                    <div class="check-value">
                        <?php echo $item['value']; ?>
                        <span class="status-badge <?php echo $item['status']; ?>">
                            <?php 
                            switch ($item['status']) {
                                case 'success': echo '满足'; break;
                                case 'error': echo '不足'; break;
                                case 'warning': echo '未知'; break;
                                case 'info': echo '信息'; break;
                            }
                            ?>
                        </span>
                    </div>
                    <?php if ($item['suggestion']): ?>
                    <div class="check-suggestion">建议: <?php echo $item['suggestion']; ?></div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="category">
            <h2>PHP扩展支持</h2>
            <ul class="check-list">
                <?php foreach ($results['extensions'] as $item): ?>
                <li class="check-item <?php echo $item['status']; ?>">
                    <div class="check-name"><?php echo $item['name']; ?></div>
                    <div class="check-value">
                        <?php echo $item['value']; ?>
                        <span class="status-badge <?php echo $item['status']; ?>">
                            <?php 
                            switch ($item['status']) {
                                case 'success': echo '已支持'; break;
                                case 'error': echo '必需'; break;
                                case 'warning': echo '推荐'; break;
                            }
                            ?>
                        </span>
                    </div>
                    <?php if ($item['suggestion']): ?>
                    <div class="check-suggestion">建议: <?php echo $item['suggestion']; ?></div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="category">
            <h2>目录权限检测</h2>
            <ul class="check-list">
                <?php foreach ($results['directories'] as $item): ?>
                <li class="check-item <?php echo $item['status']; ?>">
                    <div class="check-name"><?php echo $item['name']; ?></div>
                    <div class="check-value">
                        <?php echo $item['value']; ?>
                        <span class="status-badge <?php echo $item['status']; ?>">
                            <?php echo $item['status'] === 'success' ? '正常' : '异常'; ?>
                        </span>
                    </div>
                    <?php if ($item['suggestion']): ?>
                    <div class="check-suggestion">建议: <?php echo $item['suggestion']; ?></div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="category">
            <h2>PHP配置参数</h2>
            <ul class="check-list">
                <?php foreach ($results['php_settings'] as $item): ?>
                <li class="check-item <?php echo $item['status']; ?>">
                    <div class="check-name"><?php echo $item['name']; ?></div>
                    <div class="check-value">
                        <?php echo $item['value']; ?>
                        <span class="status-badge <?php echo $item['status']; ?>">
                            <?php echo $item['status'] === 'success' ? '满足' : '不足'; ?>
                        </span>
                    </div>
                    <?php if ($item['suggestion']): ?>
                    <div class="check-suggestion">建议: <?php echo $item['suggestion']; ?></div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="category">
            <h2>数据库配置检测</h2>
            <ul class="check-list">
                <?php foreach ($results['database'] as $item): ?>
                <li class="check-item <?php echo $item['status']; ?>">
                    <div class="check-name"><?php echo $item['name']; ?></div>
                    <div class="check-value">
                        <?php echo $item['value']; ?>
                        <span class="status-badge <?php echo $item['status']; ?>">
                            <?php 
                            switch ($item['status']) {
                                case 'success': echo '正常'; break;
                                case 'error': echo '错误'; break;
                                case 'warning': echo '警告'; break;
                                case 'info': echo '信息'; break;
                            }
                            ?>
                        </span>
                    </div>
                    <?php if ($item['suggestion']): ?>
                    <div class="check-suggestion">建议: <?php echo $item['suggestion']; ?></div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="category">
            <h2>关键函数支持</h2>
            <ul class="check-list">
                <?php foreach ($results['functions'] as $item): ?>
                <li class="check-item <?php echo $item['status']; ?>">
                    <div class="check-name"><?php echo $item['name']; ?></div>
                    <div class="check-value">
                        <?php echo $item['value']; ?>
                        <span class="status-badge <?php echo $item['status']; ?>">
                            <?php echo $item['status'] === 'success' ? '支持' : '缺失'; ?>
                        </span>
                    </div>
                    <?php if ($item['suggestion']): ?>
                    <div class="check-suggestion">建议: <?php echo $item['suggestion']; ?></div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="category">
            <h2>缓存功能检测</h2>
            <ul class="check-list">
                <?php foreach ($results['cache'] as $item): ?>
                <li class="check-item <?php echo $item['status']; ?>">
                    <div class="check-name"><?php echo $item['name']; ?></div>
                    <div class="check-value">
                        <?php echo $item['value']; ?>
                        <span class="status-badge <?php echo $item['status']; ?>">
                            <?php 
                            switch ($item['status']) {
                                case 'success': echo '正常'; break;
                                case 'error': echo '失败'; break;
                                case 'info': echo '信息'; break;
                                case 'warning': echo '警告'; break;
                            }
                            ?>
                        </span>
                    </div>
                    <?php if ($item['suggestion']): ?>
                    <div class="check-suggestion">建议: <?php echo $item['suggestion']; ?></div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="suggestions-section">
            <h2>总结建议</h2>
            <?php if ($hasError): ?>
            <p>服务器环境存在必需修复的问题，请优先解决所有标记为"错误"的项目，这些问题会导致网站无法正常运行。</p>
            <p>主要修复方向：</p>
            <ul>
                <li>确保所有必需的PHP扩展已安装并启用（包括新增的zip扩展）</li>
                <li>检查并修复所有目录权限问题（尤其是cache目录）</li>
                <li>正确配置数据库连接参数（在include/Db.php中）</li>
                <li>升级PHP版本至<?php echo $requirements['php_version']; ?>或更高</li>
                <li>确保关键函数可用且未被禁用</li>
                <li>确保服务器硬件满足最低要求（内存至少1.5GB，CPU至少2个逻辑核心）</li>
            </ul>
            <?php else: ?>
            <p>服务器环境满足网站运行要求，可以正常部署使用。</p>
            <p>优化建议：</p>
            <ul>
                <li>对于标记为"警告"的项目，建议根据提示进行优化以获得更好的性能</li>
                <li>确保数据库字符集配置为utf8mb4以支持更全面的Unicode字符</li>
                <li>定期检查缓存目录大小，避免占用过多磁盘空间</li>
                <li>监控服务器硬件资源使用情况，确保有足够的CPU和内存资源</li>
                <li>定期备份数据库中的重要数据</li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>