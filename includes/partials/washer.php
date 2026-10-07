<?php
/**
 * Washer in motion, from the Claude Design "LaundryTrack Processing Icon" component.
 * Spinning progress ring, swaying drum, rising bubbles and a slow breath (SMIL, 4.2s).
 * assets/js/motion.js pauses it off screen and under reduced motion.
 * @var bool $bare   true: only the machine (no light backdrop), as on the landing and sign-in panels
 */
if (!function_exists('washer_ring')) {
    function washer_ring(): array
    {
        $cx = 1376; $cy = 813.5; $r = 289;
        $p = fn ($a) => [$cx + $r * cos(deg2rad($a)), $cy + $r * sin(deg2rad($a))];
        $stops = [[-92, '#A0FBF6'], [-60, '#8DF4F2'], [-30, '#78E5ED'], [0, '#5DCDED'], [30, '#4FB0EF'], [60, '#4591F0'],
                  [90, '#4784F4'], [120, '#4B8BF3'], [150, '#5AB0EC'], [170, '#65CBEC'], [182, '#72DBF0']];
        $col = function ($a) use ($stops) {
            $i = 0;
            while ($i < count($stops) - 2 && $a > $stops[$i + 1][0]) $i++;
            [$a0, $c0] = $stops[$i]; [$a1, $c1] = $stops[$i + 1];
            $t = min(1, max(0, ($a - $a0) / ($a1 - $a0)));
            $rgb = [];
            for ($k = 0; $k < 3; $k++) {
                $v0 = hexdec(substr($c0, 1 + 2 * $k, 2)); $v1 = hexdec(substr($c1, 1 + 2 * $k, 2));
                $rgb[] = (int) round($v0 + ($v1 - $v0) * $t);
            }
            return 'rgb(' . implode(',', $rgb) . ')';
        };
        $segs = '';
        for ($a = -92; $a < 182; $a += 4) {
            $b = min(182, $a + 4.6);
            [$x1, $y1] = $p($a); [$x2, $y2] = $p($b);
            $segs .= sprintf('<path d="M%.2f %.2fA%d %d 0 0 1 %.2f %.2f" stroke="%s"/>', $x1, $y1, $r, $r, $x2, $y2, $col($a + 2));
        }
        return [$segs, $p(-92), $p(182)];
    }
}
$GLOBALS['lt_washer_n'] = ($GLOBALS['lt_washer_n'] ?? 0) + 1;
$u = 'w' . $GLOBALS['lt_washer_n'];
$bare = $bare ?? false;
[$segs, [$ax, $ay], [$bx, $by]] = washer_ring();
$img = '<image href="' . e(url('assets/img/washer.jpg')) . '" x="880" y="290" width="992" height="1110" preserveAspectRatio="none"';
?>
<svg class="washer" viewBox="880 290 992 1110" role="img" aria-label="Laundry is being processed" data-washer>
  <defs>
    <clipPath id="<?= $u ?>b"><rect x="1000" y="330" width="751" height="876" rx="86"/></clipPath>
    <clipPath id="<?= $u ?>r"><circle cx="1376" cy="807" r="244.5"/></clipPath>
    <clipPath id="<?= $u ?>d"><circle cx="1376" cy="807" r="202"/></clipPath>
    <clipPath id="<?= $u ?>u"><circle cx="1376" cy="807" r="196"/></clipPath>
    <linearGradient id="<?= $u ?>n" gradientUnits="userSpaceOnUse" x1="1239" y1="519" x2="1513" y2="1108"><stop offset="0" stop-color="#425D8E"/><stop offset=".12" stop-color="#3F5A8A"/><stop offset=".35" stop-color="#24386A"/><stop offset="1" stop-color="#1A2A56"/></linearGradient>
    <linearGradient id="<?= $u ?>g" gradientUnits="userSpaceOnUse" x1="1239" y1="519" x2="1513" y2="1108"><stop offset="0" stop-color="#5A7EB1"/><stop offset=".12" stop-color="#557DAE"/><stop offset=".35" stop-color="#37578F"/><stop offset="1" stop-color="#2A4A86"/></linearGradient>
  </defs>
  <?php if (!$bare): ?><?= $img ?>/><?php endif; ?>
  <g transform="translate(1375.5 1206)"><g>
    <animateTransform attributeName="transform" type="scale" values="1;1.008;1" keyTimes="0;.5;1" calcMode="spline" keySplines=".45 0 .55 1;.45 0 .55 1" dur="4.2s" repeatCount="indefinite"/>
    <g transform="translate(-1375.5 -1206)">
      <?= $img ?> clip-path="url(#<?= $u ?>b)"/>
      <circle cx="1376" cy="813.5" r="324" fill="url(#<?= $u ?>n)"/>
      <circle cx="1376" cy="813.5" r="289" fill="none" stroke="url(#<?= $u ?>g)" stroke-width="36"/>
      <g fill="none" stroke-width="36">
        <animateTransform attributeName="transform" type="rotate" from="0 1376 813.5" to="360 1376 813.5" dur="4.2s" repeatCount="indefinite"/>
        <?= $segs ?>
        <circle cx="<?= round($ax, 2) ?>" cy="<?= round($ay, 2) ?>" r="18" fill="#A0FBF6" stroke="none"/>
        <circle cx="<?= round($bx, 2) ?>" cy="<?= round($by, 2) ?>" r="18" fill="#72DBF0" stroke="none"/>
      </g>
      <?= $img ?> clip-path="url(#<?= $u ?>r)"/>
      <g clip-path="url(#<?= $u ?>d)"><g>
        <animateTransform attributeName="transform" type="rotate" values="-2.5 1376 807;2.5 1376 807;-2.5 1376 807" keyTimes="0;.5;1" calcMode="spline" keySplines=".45 0 .55 1;.45 0 .55 1" dur="4.2s" repeatCount="indefinite"/>
        <?= $img ?>/>
      </g></g>
      <g clip-path="url(#<?= $u ?>u)" aria-hidden="true">
        <?php foreach ([[1290, 9, 4.6, -0.5, 10], [1420, 13, 5.4, -2.2, -14], [1350, 7, 3.8, -3.1, 6], [1470, 8, 4.2, -1.4, -8], [1240, 11, 5.0, -3.9, 12]] as [$x, $r, $d, $b, $dx]): ?>
          <g opacity="0">
            <animateTransform attributeName="transform" type="translate" values="0 0;<?= $dx ?> -300" dur="<?= $d ?>s" begin="<?= $b ?>s" repeatCount="indefinite" calcMode="spline" keyTimes="0;1" keySplines=".3 0 .6 1"/>
            <animate attributeName="opacity" values="0;.95;.95;0" keyTimes="0;.18;.7;1" dur="<?= $d ?>s" begin="<?= $b ?>s" repeatCount="indefinite"/>
            <circle cx="<?= $x ?>" cy="960" r="<?= $r ?>" fill="rgba(160,205,255,.22)" stroke="rgba(215,236,255,.75)" stroke-width="2.5"/>
            <circle cx="<?= $x - $r * .35 ?>" cy="<?= 960 - $r * .35 ?>" r="<?= $r * .28 ?>" fill="rgba(255,255,255,.85)"/>
          </g>
        <?php endforeach; ?>
      </g>
    </g>
  </g></g>
</svg>
