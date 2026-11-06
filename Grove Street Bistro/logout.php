<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

logout_user();

// Start a fresh session purely to carry the confirmation message
session_start();
flash('You are signed out.');
redirect('index.php');
