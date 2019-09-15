<!doctype html>

<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Script Scanner</title>
</head>

<body>
    <h1>Script Scanner</h1>

    <?php if (!file_exists(__DIR__ . '/../data/environments-with-script-data.json')) { ?><p>Use the CLI tool to scan SCP environments for defined scripts.</p><?php } ?>

    <div class="app" data-environments="<?php echo file_get_contents(__DIR__ . '/../data/environments-with-script-data.json') ?>">
        <p>(Script information to be rendered here at some point)</p>
    </div>
</body>

</html>
