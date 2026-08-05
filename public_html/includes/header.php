<?php
// Shared Header Template Include
if (!isset($page_title)) {
    $page_title = "Boiyets Fitness Gym";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Boiyets Fitness Gym Management System — Manage members, attendance, equipment, revenue, and more.">
    <script>
      (function() {
        const savedTheme = localStorage.getItem('gym_theme') || 'dark';
        const savedAccent = localStorage.getItem('gym_accent') || 'gold';
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.documentElement.setAttribute('data-accent', savedAccent);
      })();
    </script>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏋️</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              gymbg: '#0b0f19',
              gymcard: '#131a2b',
              gymaccent: '#e8a012'
            }
          }
        }
      }
    </script>
    <link rel="stylesheet" href="assets/css/gym_layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="assets/js/command_palette.js" defer></script>
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['trainer', 'admin'])): ?>
    <script src="assets/js/qr_scanner_global.js" defer></script>
    <?php endif; ?>
</head>
<body class="bg-[#0b0f19] text-[#e8ecf4]">
