<?php
// Function to get human-readable file size
function humanFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
    return round($size, 2) . ' ' . $units[$i];
}

// Get current directory
$dir = isset($_GET['dir']) ? $_GET['dir'] : '.';
$parentDir = dirname($dir); // Get parent directory
$files = scandir($dir);
$files = array_diff($files, ['.', '..']); // Remove . and ..
$files = array_filter($files, function($file) {
    return !(strpos($file, '.') === 0); // Hide dotfiles
});

// Sorting logic
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc'; // Default to ascending

if ($sort === 'name') {
    natsort($files);
    if ($order === 'desc') {
        $files = array_reverse($files);
    }
} elseif ($sort === 'size') {
    usort($files, function($a, $b) use ($dir) {
        return filesize("$dir/$a") <=> filesize("$dir/$b");
    });
    if ($order === 'desc') {
        $files = array_reverse($files);
    }
} elseif ($sort === 'type') {
    usort($files, function($a, $b) use ($dir) {
        return mime_content_type("$dir/$a") <=> mime_content_type("$dir/$b");
    });
    if ($order === 'desc') {
        $files = array_reverse($files);
    }
}

// Get list of files for navigation
$fileList = array_values(array_filter($files, function($file) use ($dir) {
    return !is_dir("$dir/$file");
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directory Lister</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Directory Lister</h1>
        <div class="toggle-switch">
            <label class="switch-label">
                <input type="checkbox" class="checkbox" id="dark-mode-toggle">
                <span class="slider"></span>
            </label>
        </div>
    </header>

    <!-- Back Button -->
    <?php if ($dir !== '.'): ?>
        <div class="back-button">
            <a href="?dir=<?= urlencode($parentDir) ?>&sort=<?= $sort ?>&order=<?= $order ?>">⬅ Back</a>
        </div>
    <?php endif; ?>

    <!-- Sorter -->
    <div class="sorter-container">
        <div class="pane">
            <label class="label">
                <span>Name</span>
                <input id="left" class="input" name="radio" type="radio" onclick="sortFiles('name')" <?= $sort === 'name' ? 'checked' : '' ?>>
            </label>
            <label class="label">
                <span>Size</span>
                <input id="middle" class="input" name="radio" type="radio" onclick="sortFiles('size')" <?= $sort === 'size' ? 'checked' : '' ?>>
            </label>
            <label class="label">
                <span>Type</span>
                <input id="right" class="input" name="radio" type="radio" onclick="sortFiles('type')" <?= $sort === 'type' ? 'checked' : '' ?>>
            </label>
            <span class="selection"></span>
        </div>
    </div>

    <!-- File List -->
    <div class="file-list">
        <?php foreach ($files as $file): ?>
            <?php
            $filePath = "$dir/$file";
            $isDir = is_dir($filePath);
            $fileSize = $isDir ? 'Folder' : humanFileSize(filesize($filePath));
            $fileType = $isDir ? 'Folder' : mime_content_type($filePath);
            ?>
            <div class="file-item">
                <span class="file-name">
                    <?php if ($isDir): ?>
                        <a href="?dir=<?= urlencode($filePath) ?>&sort=<?= $sort ?>&order=<?= $order ?>">📁 <?= $file ?></a>
                    <?php else: ?>
                        <?= $file ?>
                    <?php endif; ?>
                </span>
                <span class="file-size"><?= $fileSize ?></span>
                <span class="file-type"><?= $fileType ?></span>
                <?php if (!$isDir): ?>
                    <a href="<?= $filePath ?>" class="download-button" download>Download</a>
                    <button onclick="viewFile('<?= $filePath ?>', <?= array_search($file, $fileList) ?>)">View</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- File Viewer Modal -->
    <div id="file-viewer" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeViewer()">&times;</span>
            <div class="file-viewer-header">
                <span id="file-viewer-name"></span>
            </div>
            <div class="navigation-buttons">
                <button id="prev-button" onclick="navigateFile(-1)">⬅ Previous</button>
                <button id="next-button" onclick="navigateFile(1)">Next ➡</button>
            </div>
            <iframe id="file-frame" src=""></iframe>
        </div>
    </div>

    <script>
        // Dark mode toggle
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        darkModeToggle.addEventListener('change', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('dark-mode', darkModeToggle.checked);
        });

        // Load dark mode preference
        if (localStorage.getItem('dark-mode') === 'true') {
            document.body.classList.add('dark-mode');
            darkModeToggle.checked = true;
        }

        // Sorting function
        function sortFiles(sortBy) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentOrder = urlParams.get('order');

            let newOrder = 'asc';
            if (currentSort === sortBy) {
                newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            }

            window.location.href = `?dir=<?= urlencode($dir) ?>&sort=${sortBy}&order=${newOrder}`;
        }

        // File viewer functions
        let currentFileIndex = 0;
        const fileList = <?= json_encode($fileList) ?>;

        function viewFile(filePath, index) {
            const modal = document.getElementById('file-viewer');
            const frame = document.getElementById('file-frame');
            const fileNameDisplay = document.getElementById('file-viewer-name');
            currentFileIndex = index;
            frame.src = filePath;
            fileNameDisplay.textContent = fileList[currentFileIndex];
            modal.style.display = 'block';
            updateNavigationButtons();
        }

        function closeViewer() {
            const modal = document.getElementById('file-viewer');
            modal.style.display = 'none';
        }

        function navigateFile(step) {
            currentFileIndex += step;
            if (currentFileIndex < 0) currentFileIndex = fileList.length - 1;
            if (currentFileIndex >= fileList.length) currentFileIndex = 0;
            const filePath = `<?= $dir ?>/${fileList[currentFileIndex]}`;
            document.getElementById('file-frame').src = filePath;
            document.getElementById('file-viewer-name').textContent = fileList[currentFileIndex];
            updateNavigationButtons();
        }

        function updateNavigationButtons() {
            document.getElementById('prev-button').disabled = fileList.length <= 1;
            document.getElementById('next-button').disabled = fileList.length <= 1;
        }
    </script>
</body>
</html>
