<?php
session_start();

$id = $_GET['id'] ?? '';
$r = null;

// Try to load receipt from session first, then from disk
if ($id !== '') {
    if (isset($_SESSION['mock_receipts'][$id])) {
        $r = $_SESSION['mock_receipts'][$id];
    } else {
        // Try disk file
        $filePath = __DIR__ . DIRECTORY_SEPARATOR . '_receipts' . DIRECTORY_SEPARATOR . $id . '.json';
        if (is_file($filePath)) {
            $raw = @file_get_contents($filePath);
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $r = $decoded;
            }
        }
    }
}

function h($s){ return htmlspecialchars($s ?? ''); }

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .receipt { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 20px; }
        .amount { font-size: 32px; font-weight: bold; color: #28a745; text-align: center; margin: 20px 0; }
        .details { margin: 15px 0; }
        .details strong { display: inline-block; width: 80px; }
        .actions { margin-top: 30px; text-align: center; }
        .btn { display: inline-block; padding: 12px 20px; margin: 5px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn.download { background: #28a745; }
        .btn.download:hover { background: #1e7e34; }
        .download-menu { display: none; position: absolute; background: white; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 1000; }
        .download-menu button { display: block; width: 100%; padding: 10px 15px; border: none; background: none; text-align: left; cursor: pointer; }
        .download-menu button:hover { background: #f8f9fa; }
        @media print { .actions, .no-print { display: none !important; } body { background: white; } }
    </style>
</head>
<body>
    <div class="receipt" id="receipt">
        <?php if ($r): ?>
            <div class="header">
                <h1>Payment Receipt</h1>
                <p>Transaction ID: <strong><?php echo h($r['id']); ?></strong></p>
            </div>
            
            <div class="amount">₹<?php echo number_format($r['amount'], 2); ?></div>
            
            <div class="details">
                <p><strong>From:</strong> <?php echo h($r['from']); ?></p>
                <p><strong>To:</strong> <?php echo h($r['to']); ?></p>
                <p><strong>Note:</strong> <?php echo h($r['note'] ?: '—'); ?></p>
                <p><strong>Date:</strong> <?php echo h($r['created_at']); ?></p>
                <p><strong>Status:</strong> <span style="color: #28a745; font-weight: bold;">Completed</span></p>
            </div>
            
            <div class="actions no-print">
                <button class="btn" onclick="copyLink()">Copy Link</button>
                <button class="btn" onclick="shareReceipt()">Share</button>
                <div style="position: relative; display: inline-block;">
                    <button class="btn download" onclick="toggleDownloadMenu()">Download</button>
                    <div class="download-menu" id="downloadMenu">
                        <button onclick="downloadAsImage()">📷 Save as Image</button>
                        <button onclick="downloadAsText()">📄 Save as Text</button>
                        <button onclick="window.print()">🖨️ Print PDF</button>
                    </div>
                </div>
                <button class="btn" onclick="window.print()">Print</button>
            </div>
            
        <?php else: ?>
            <div class="header">
                <h1>Receipt Not Found</h1>
                <p>The requested receipt could not be found.</p>
            </div>
            <div class="actions">
                <a href="phone.html" class="btn">Send Money</a>
                <a href="homepage_v1.php" class="btn">Dashboard</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('Receipt link copied to clipboard!');
            });
        }

        function shareReceipt() {
            if (navigator.share) {
                navigator.share({
                    title: 'Payment Receipt',
                    text: 'Payment of ₹<?php echo number_format($r['amount'] ?? 0, 2); ?> completed successfully',
                    url: window.location.href
                });
            } else {
                copyLink();
            }
        }

        function toggleDownloadMenu() {
            const menu = document.getElementById('downloadMenu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }

        // Close download menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('downloadMenu');
            const button = event.target.closest('.download');
            if (!button && menu.style.display === 'block') {
                menu.style.display = 'none';
            }
        });

        function downloadAsImage() {
            // Create a canvas and draw the receipt
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 400;
            canvas.height = 350;
            
            // White background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Draw receipt content
            ctx.fillStyle = '#000000';
            ctx.font = 'bold 18px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Payment Receipt', canvas.width/2, 30);
            
            ctx.font = '14px Arial';
            ctx.fillText('Transaction ID: <?php echo h($r['id'] ?? ''); ?>', canvas.width/2, 50);
            
            ctx.font = 'bold 28px Arial';
            ctx.fillStyle = '#28a745';
            ctx.fillText('₹<?php echo number_format($r['amount'] ?? 0, 2); ?>', canvas.width/2, 90);
            
            ctx.fillStyle = '#000000';
            ctx.font = '14px Arial';
            ctx.textAlign = 'left';
            
            let y = 130;
            ctx.fillText('From: <?php echo h($r['from'] ?? ''); ?>', 20, y);
            y += 25;
            ctx.fillText('To: <?php echo h($r['to'] ?? ''); ?>', 20, y);
            y += 25;
            ctx.fillText('Note: <?php echo h($r['note'] ?: '—'); ?>', 20, y);
            y += 25;
            ctx.fillText('Date: <?php echo h($r['created_at'] ?? ''); ?>', 20, y);
            y += 25;
            ctx.fillStyle = '#28a745';
            ctx.fillText('Status: Completed', 20, y);
            
            // Download
            const link = document.createElement('a');
            link.download = 'receipt-<?php echo h($r['id'] ?? 'payment'); ?>.png';
            link.href = canvas.toDataURL();
            link.click();
            
            toggleDownloadMenu();
        }

        function downloadAsText() {
            const text = `PAYMENT RECEIPT
================
Transaction ID: <?php echo h($r['id'] ?? ''); ?>
Amount: ₹<?php echo number_format($r['amount'] ?? 0, 2); ?>
From: <?php echo h($r['from'] ?? ''); ?>
To: <?php echo h($r['to'] ?? ''); ?>
Note: <?php echo h($r['note'] ?: '—'); ?>
Date: <?php echo h($r['created_at'] ?? ''); ?>
Status: Completed

Thank you for using our service!`;

            const blob = new Blob([text], { type: 'text/plain' });
            const link = document.createElement('a');
            link.download = 'receipt-<?php echo h($r['id'] ?? 'payment'); ?>.txt';
            link.href = URL.createObjectURL(blob);
            link.click();
            
            toggleDownloadMenu();
        }
    </script>
</body>
</html>
