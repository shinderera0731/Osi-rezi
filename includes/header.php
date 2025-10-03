<?php
// includes/header.php
/**
 * 共通HTMLヘッダー
 */

$page_title = $page_title ?? 'Cinderella cafe';
$page_subtitle = $page_subtitle ?? 'カフェ管理システム';
?>

<div class="header">
    <h1>🏰 <?php echo htmlspecialchars($page_title); ?></h1>
    <p><?php echo htmlspecialchars($page_subtitle); ?></p>
</div>