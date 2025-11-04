<?php
return array (
  'content' => '<span id="span"></span>
<script type="text/javascript">
    function runtime() {
        const X = new Date("10/26/2025 00:00:00"); 
        const Y = new Date();
        const T = Y.getTime() - X.getTime();
        if (T < 0) {
            span.innerHTML = "本网站已运行: 0天0小时0分0秒";
            return;
        }
        const M = 24 * 60 * 60 * 1000;
        const totalDays = T / M;
        const A = Math.floor(totalDays);

        const remainingHours = (totalDays - A) * 24;
        const B = Math.floor(remainingHours);

        const remainingMinutes = (remainingHours - B) * 60;
        const C = Math.floor(remainingMinutes);

        const D = Math.floor((remainingMinutes - C) * 60);

        span.innerHTML = `本网站已运行: ${A}天${B}小时${C}分${D}秒`;
    }
    setInterval(runtime, 1000);
    runtime();
</script>',
  'css' => '',
  'js' => '',
  'updated_at' => 1761896315,
);
