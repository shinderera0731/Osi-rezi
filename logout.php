<?php
// logout.php - ★最終リファクタリング版

// --- 共通設定の読み込み ---
// スタッフの出入りを管理する専門家（警備員さん）だけを呼び出します
require_once 'config/auth.php';

// 警備員さん(logoutUser関数)に、安全なログアウト処理をすべてお任せします
logoutUser();

// ★重要：logoutUser()で一度セッションが完全に終了するため、
// メッセージを表示するためには、もう一度セッションを開始する必要があります。
session_start();

// ログアウトしたことを伝えるメッセージを準備します
$_SESSION['message'] = 'ログアウトしました。';

// レストランの玄関（index.php）にお客さんを案内します
header('Location: index.php');
exit();
