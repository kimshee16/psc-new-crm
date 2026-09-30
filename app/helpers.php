<?php

if (! function_exists('payroll_icon')) {
    function payroll_icon(string $name, string $class = 'h-4 w-4'): string
    {
        $paths = [
            'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>',
            'applications' => '<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4"/><path d="M8 12h8"/><path d="M8 16h6"/>',
            'leads' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
            'clients' => '<path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><path d="M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M19 8v6"/><path d="M16 11h6"/>',
            'organisations' => '<path d="M4 21V6l8-3 8 3v15"/><path d="M9 21v-6h6v6"/><path d="M8 9h.01M12 9h.01M16 9h.01M8 12h.01M12 12h.01M16 12h.01"/>',
            'graduation' => '<path d="m22 10-10-5-10 5 10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/><path d="M22 10v6"/>',
            'services' => '<path d="M12 2 4 6v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6l-8-4Z"/><path d="m9 12 2 2 4-5"/>',
            'tasks' => '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="m4 6 1 1 2-2"/><path d="m4 12 1 1 2-2"/><path d="m4 18 1 1 2-2"/>',
            'rfi' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4"/><path d="M12 17h.01"/>',
            'rfis' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4"/><path d="M12 17h.01"/>',
            'my-offices' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-7h6v7"/><path d="M9 9h.01M12 9h.01M15 9h.01"/>',
            'reporting' => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-3"/>',
            'reports' => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-3"/>',
            'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
            'upload' => '<path d="M12 3v12"/><path d="m7 8 5-5 5 5"/><path d="M5 15v4h14v-4"/>',
            'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a8 8 0 0 0 .1-1 8 8 0 0 0-.1-1l2-1.5-2-3.4-2.4 1a7.4 7.4 0 0 0-1.7-1L15 5.5h-4l-.3 2.6c-.6.2-1.2.6-1.7 1l-2.4-1-2 3.4 2 1.5a8 8 0 0 0-.1 1 8 8 0 0 0 .1 1l-2 1.5 2 3.4 2.4-1c.5.4 1.1.8 1.7 1l.3 2.6h4l.3-2.6c.6-.2 1.2-.6 1.7-1l2.4 1 2-3.4-2-1.5Z"/>',
            'building' => '<path d="M4 21V6l8-3 8 3v15"/><path d="M9 21v-6h6v6"/><path d="M8 9h.01M12 9h.01M16 9h.01M8 12h.01M12 12h.01M16 12h.01"/>',
            'columns' => '<path d="M4 5h16v14H4z"/><path d="M9 5v14M15 5v14"/>',
            'grid' => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/>',
            'calendar' => '<path d="M7 3v4M17 3v4M4 9h16M5 5h14v16H5z"/>',
            'sliders' => '<path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h8M16 18h4"/><path d="M14 4v4M8 10v4M14 16v4"/>',
            'arrows' => '<path d="m7 7-4 4 4 4"/><path d="M3 11h15"/><path d="m17 17 4-4-4-4"/><path d="M21 13H6"/>',
            'file' => '<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4"/>',
            'calculator' => '<path d="M6 3h12v18H6z"/><path d="M8 7h8M8 11h2M12 11h2M16 11h.01M8 15h2M12 15h2M16 15h.01"/>',
            'chart' => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="m7 15 3-4 3 2 5-7"/>',
            'dollar' => '<path d="M12 2v20M17 6.5C15.8 5.4 14.2 5 12.7 5 10.2 5 8.5 6.2 8.5 8s1.5 2.8 4 3.4c2.6.7 4 1.6 4 3.6 0 1.9-1.7 3.2-4.2 3.2-1.8 0-3.6-.6-4.8-1.8"/>',
            'arrow-right' => '<path d="M9 18l6-6-6-6"/>',
            'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 19h14"/>',
            'filter' => '<path d="M4 5h16l-6 7v5l-4 2v-7z"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>',
            'edit' => '<path d="M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
            'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3"/>',
            'search' => '<path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z"/><path d="m16 16 5 5"/>',
        ];

        $path = $paths[$name] ?? $paths['home'];

        return '<svg width="24" height="24" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';
    }
}
