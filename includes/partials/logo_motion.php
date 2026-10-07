<?php
/**
 * Logo mark with its status, from the Claude Design "LaundryTrack Logo Motion" component.
 * processing: the shirt lifts, the fold flips and an arc sweeps around (driven by assets/js/motion.js)
 * pending: dotted ring · done: green check · idle: still mark
 * @var string $lmState  @var string $lmClass
 */
$GLOBALS['lt_logo_paths'] = $GLOBALS['lt_logo_paths'] ?? require __DIR__ . '/../logo_paths.php';
$GLOBALS['lt_lm_n'] = ($GLOBALS['lt_lm_n'] ?? 0) + 1;
$logoPaths = $GLOBALS['lt_logo_paths'];
$gid = 'lm' . $GLOBALS['lt_lm_n'];
$lmState = $lmState ?? 'processing';
$lmLabel = ['processing' => 'Laundry is being processed', 'pending' => 'Waiting for the shop', 'done' => 'Laundry is ready'][$lmState] ?? 'LaundryTrack';
?>
<svg class="lm <?= e($lmClass ?? '') ?>" viewBox="-12 -12 536 536" role="img" aria-label="<?= e($lmLabel) ?>" data-lm-state="<?= e($lmState) ?>">
  <defs><linearGradient id="<?= $gid ?>" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#343A8C"/><stop offset="1" stop-color="#1B1E4D"/></linearGradient></defs>
  <path d="<?= $logoPaths['bg'] ?>" fill="url(#<?= $gid ?>)"/>
  <circle class="lm-pending" cx="256" cy="256" r="212" fill="none" stroke="#9AA2FF" stroke-width="7" stroke-linecap="round" stroke-dasharray="0 26" opacity=".75"/>
  <path data-lm="arc" d="M256 44" fill="none" stroke="#9AA2FF" stroke-width="7" stroke-linecap="round" opacity="0"/>
  <g data-lm="shirt">
    <path d="<?= $logoPaths['shirt'] ?>" fill="#FFFFFF"/>
    <path data-lm="fold" d="<?= $logoPaths['fold'] ?>" fill="#9AA2FF"/>
  </g>
  <g class="lm-done">
    <circle cx="456" cy="56" r="58" fill="#2D9D5A" stroke="var(--lm-ring, #FAF7F2)" stroke-width="14"/>
    <path d="M428 57l19 19l37-39" fill="none" stroke="#FFFFFF" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>
  </g>
</svg>
