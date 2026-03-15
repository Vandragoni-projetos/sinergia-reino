<?php
/**
 * Badges pré-definidos para gamificação
 * Retorna data URI SVG para chaves badge-1 a badge-17
 */
function get_badge_display_url($key) {
    if (empty($key)) return null;
    if (strpos($key, 'http') === 0 || strpos($key, '/') === 0) {
        return $key;
    }
    $badges = get_predefined_badges();
    return $badges[$key] ?? null;
}

function get_predefined_badges() {
    static $badges = null;
    if ($badges !== null) return $badges;
    $s = function($svg) { return 'data:image/svg+xml,' . rawurlencode($svg); };
    $badges = [
        'badge-1' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="#8b5cf6" stroke="#a78bfa" stroke-width="2"/><path d="M32 18l4 8 9 1-6 6 2 9-9-5-9 5 2-9-6-6 9-1z" fill="#fbbf24"/></svg>'),
        'badge-2' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><polygon points="32,4 56,20 56,44 32,60 8,44 8,20" fill="#3b82f6" stroke="#60a5fa" stroke-width="2"/><path d="M32 22l3 6 7 1-5 5 1 7-6-4-6 4 1-7-5-5 7-1z" fill="#fff"/></svg>'),
        'badge-3' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="#eab308" stroke="#facc15" stroke-width="2"/><path d="M32 20l2 4 5 1-3 3 1 5-5-3-5 3 1-5-3-3 5-1z" fill="#22c55e"/></svg>'),
        'badge-4' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="#eab308" stroke="#facc15" stroke-width="2"/><path d="M32 18l4 8 9 1-6 6 2 9-9-5-9 5 2-9-6-6 9-1z" fill="#fff"/></svg>'),
        'badge-5' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="#22c55e" stroke="#4ade80" stroke-width="2"/><path d="M24 32l6 6 12-12" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round"/></svg>'),
        'badge-6' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><polygon points="32,4 56,20 56,44 32,60 8,44 8,20" fill="#22c55e" stroke="#4ade80" stroke-width="2"/><path d="M24 32l6 6 12-12" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round"/></svg>'),
        'badge-7' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><polygon points="32,4 56,20 56,44 32,60 8,44 8,20" fill="#3b82f6" stroke="#60a5fa" stroke-width="2"/><text x="32" y="38" text-anchor="middle" fill="#fff" font-size="20" font-weight="bold">V</text></svg>'),
        'badge-8' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><polygon points="32,2 58,18 58,46 32,62 6,46 6,18" fill="#3b82f6" stroke="#60a5fa" stroke-width="2"/><path d="M32 18l4 8 9 1-6 6 2 9-9-5-9 5 2-9-6-6 9-1z" fill="#fbbf24"/></svg>'),
        'badge-9' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="#8b5cf6" stroke="#a78bfa" stroke-width="2"/><path d="M32 18l4 8 9 1-6 6 2 9-9-5-9 5 2-9-6-6 9-1z" fill="#fff"/></svg>'),
        'badge-10' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><polygon points="32,4 56,20 56,44 32,60 8,44 8,20" fill="#f97316" stroke="#fb923c" stroke-width="2"/><path d="M32 18l4 8 9 1-6 6 2 9-9-5-9 5 2-9-6-6 9-1z" fill="#fff"/></svg>'),
        'badge-11' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="#eab308" stroke="#facc15" stroke-width="2"/><circle cx="32" cy="30" r="12" fill="#fbbf24"/><path d="M32 22v16M26 30h12" stroke="#fff" stroke-width="2"/></svg>'),
        'badge-12' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><polygon points="32,4 56,20 56,44 32,60 8,44 8,20" fill="#f97316" stroke="#fb923c" stroke-width="2"/><path d="M32 24l8 8-8 8-8-8z" fill="#fff"/></svg>'),
        'badge-13' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="#991b1b" stroke="#b91c1c" stroke-width="2"/><path d="M32 20l2 6 6 1-4 4 1 6-5-3-5 3 1-6-4-4 6-1z" fill="#fff"/></svg>'),
        'badge-14' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><polygon points="32,2 58,18 58,46 32,62 6,46 6,18" fill="#8b5cf6" stroke="#a78bfa" stroke-width="2"/><path d="M28 36l4 4 8-12" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>'),
        'badge-15' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><rect x="8" y="16" width="48" height="32" rx="6" fill="#22c55e" stroke="#4ade80" stroke-width="2"/><path d="M20 32h8M20 36h12M20 40h8" stroke="#fff" stroke-width="2"/></svg>'),
        'badge-16' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><polygon points="32,2 58,18 58,46 32,62 6,46 6,18" fill="#dc2626" stroke="#ef4444" stroke-width="2"/><path d="M32 24l4 8 4-4 4 4-2-8 6-2-6-2-2-6-2 6-6 2z" fill="#fff"/></svg>'),
        'badge-17' => $s('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#32e768" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>'),
    ];
    return $badges;
}
