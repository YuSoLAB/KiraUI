<?php
require_once dirname(__DIR__) . '/include/Config.php';
$config = Config::getInstance();
$footerConfig = [
    'content' => $config->get('footer_content', ''),
    'css' => $config->get('footer_css', ''),
    'js' => $config->get('footer_js', '')
];
?>
<footer class="site-footer">
    <?php echo $footerConfig['content'];  ?>
    <style><?php echo $footerConfig['css'];  ?></style>
    <script><?php echo $footerConfig['js'];  ?></script>
</footer>