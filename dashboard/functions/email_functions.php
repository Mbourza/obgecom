<?php
function formatEmailDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 3600) {
        return ceil($diff / 60) . ' min';
    } elseif ($diff < 86400) {
        return ceil($diff / 3600) . ' h';
    } elseif ($diff < 604800) {
        return ceil($diff / 86400) . ' j';
    } else {
        return date('d/m/Y', $timestamp);
    }
}

function sanitizeEmailContent($content) {
    return htmlspecialchars(strip_tags($content));
}

function getEmailPriority($subject) {
    $urgentKeywords = ['urgent', 'important', 'critical', 'asap'];
    $subject = strtolower($subject);
    
    foreach ($urgentKeywords as $keyword) {
        if (strpos($subject, $keyword) !== false) {
            return 'high';
        }
    }
    return 'normal';
}
?>