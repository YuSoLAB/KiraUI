<?php
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$siteConfig = [
    'badge_text' => $config->get('badge_text', '📝 KiraUI'),
    'site_title' => $config->get('site_title', '测试网站'),
    'welcome_text' => $config->get('welcome_text', '这是一个网站'),
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_config') {
        $newConfig = [
            'badge_text' => $_POST['badge_text'] ?? '',
            'site_title' => $_POST['site_title'] ?? '',
            'welcome_text' => $_POST['welcome_text'] ?? '',
        ];
        $config->batchSet($newConfig);
        $siteConfig = array_merge($siteConfig, $newConfig);
        $message = "网站信息已保存成功！";
    }
    if (isset($_POST['action']) && $_POST['action'] === 'upload_image') {
        $uploadDir = ROOT_DIR . '/img/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }        
        if (!empty($_FILES['logo']['name'])) {
            $logoExt = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            if (strtolower($logoExt) === 'ico') {
                $logoPath = $uploadDir . 'logo.ico';
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
                    $message = "Logo上传成功！";
                } else {
                    $error = "Logo上传失败";
                }
            } else {
                $error = "Logo必须是.ico格式";
            }
        }
        if (!empty($_FILES['banner']['name'])) {
            $bannerExt = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($bannerExt), ['png', 'jpg', 'jpeg', 'gif'])) {
                $existingBanners = glob($uploadDir . 'banner*.png');
                $maxNum = 0;
                foreach ($existingBanners as $file) {
                    if (preg_match('/banner(\d+)\.png/', basename($file), $matches)) {
                        $num = intval($matches[1]);
                        if ($num > $maxNum) {
                            $maxNum = $num;
                        }
                    }
                }
                $bannerCount = $maxNum + 1;
                $bannerPath = $uploadDir . "banner{$bannerCount}.png";
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $bannerPath)) {
                    $message = $message ?? "" . "背景图片上传成功！";
                } else {
                    $error = $error ?? "" . "背景图片上传失败";
                }
            } else {
                $error = "背景图片必须是png/jpg/jpeg/gif格式";
            }
        }
    }
}
$imgDir = ROOT_DIR . '/img/';
$banners = [];
if (file_exists($imgDir)) {
    $banners = glob($imgDir . 'banner*.png');
    $banners = array_map(function($path) {
        return basename($path);
    }, $banners);
}
$hasLogo = file_exists($imgDir . 'logo.ico');
?>
<div class="tab-content" id="siteinfo">
    <div class="section">
        <h2>网站信息配置</h2>
        <p class="section-description">配置网站基本信息、Logo和背景图片</p>        
        <?php if (isset($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" class="config-form">
            <input type="hidden" name="action" value="save_config">
            <div class="form-group">
                <label for="badge_text">Badge 文本</label>
                <input type="text" id="badge_text" name="badge_text" 
                       value="<?php echo htmlspecialchars($siteConfig['badge_text']); ?>" required>
            </div>
            <div class="form-group">
                <label for="site_title">网站标题</label>
                <input type="text" id="site_title" name="site_title" 
                       value="<?php echo htmlspecialchars($siteConfig['site_title']); ?>" required>
            </div>
            <div class="form-group">
                <label for="welcome_text">欢迎词</label>
                <textarea id="welcome_text" name="welcome_text" rows="3"><?php echo htmlspecialchars($siteConfig['welcome_text']); ?></textarea>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">保存网站信息</button>
            </div>
        </form>
        <form method="post" enctype="multipart/form-data" class="image-upload-form" style="margin-top: 30px;">
            <input type="hidden" name="action" value="upload_image">
            <h3>图片上传</h3>
            <div class="form-group">
                <label for="logo">网站Logo (必须为ico格式，文件名自动设为logo.ico)</label>
                <input type="file" id="logo" name="logo" accept=".ico">
                <?php if ($hasLogo): ?>
                    <p>当前已有Logo，上传将覆盖现有文件</p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="banner">背景图片 (支持png/jpg/jpeg/gif，自动命名为banner1.png, banner2.png...)</label>
                <input type="file" id="banner" name="banner" accept="image/*">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">上传图片</button>
            </div>
        </form>
        <?php if (!empty($banners) || $hasLogo): ?>
            <div class="existing-images" style="margin-top: 30px;">
                <h3>现有图片</h3>
                <?php if ($hasLogo): ?>
                    <div class="image-item">
                        <h4>Logo</h4>
                        <img src="../img/logo.ico" alt="Logo" style="max-height: 100px;">
                    </div>
                <?php endif; ?>
                <?php if (!empty($banners)): ?>
                    <div class="banners">
                        <h4>背景图片</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php foreach ($banners as $banner): ?>
                                <div style="text-align: center;">
                                    <img src="../img/<?php echo $banner; ?>" alt="<?php echo $banner; ?>" style="max-height: 150px; max-width: 200px;">
                                    <p><?php echo $banner; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>