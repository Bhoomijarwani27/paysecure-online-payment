<?php
session_start();

$id = $_GET['id'] ?? '';
$status = $_GET['status'] ?? 'success';
$error = $_GET['error'] ?? '';

function h($s){ return htmlspecialchars($s); }

if ($id !== '' && isset($_SESSION['mock_receipts'][$id])) {
  $r = $_SESSION['mock_receipts'][$id];
} else {
  // Try to load from disk-backed receipts so the link is shareable across sessions
  $r = null;
  if ($id !== '') {
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

// Build absolute shareable URL for this page
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/';
$selfUrl = $scheme . '://' . $host . $base . 'payment_result.php' . ($id ? ('?id=' . urlencode($id)) : '');

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Payment Result</title>
  <style>
    body{font-family:Inter,Arial,sans-serif;background:#f6fbff;color:#123;padding:28px}
    .card{max-width:720px;margin:40px auto;background:#fff;border-radius:12px;padding:22px;box-shadow:0 12px 40px rgba(0,0,0,0.06)}
    .amount{font-size:34px;font-weight:700;color:#0b8043}
    .failed{color:#d32f2f}
    .meta{color:#666}
    .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;flex-wrap:wrap}
    .btn{padding:10px 14px;border-radius:8px;border:0;cursor:pointer;text-decoration:none;display:inline-block;text-align:center;min-width:80px}
    .btn.primary{background:#1d9bf0;color:#fff}
    .btn.secondary{background:#eef6ff;color:#1d9bf0}
    .linkbox{background:#fafafa;border:1px solid #eee;padding:10px;border-radius:8px;word-break:break-all}
    .receipt-content{margin-bottom:20px}
    .download-options{display:none;position:absolute;background:#fff;border:1px solid #ccc;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);padding:8px;z-index:1000;min-width:120px}
    .download-options button{display:block;width:100%;padding:8px 12px;border:none;background:none;cursor:pointer;text-align:left;border-radius:4px}
    .download-options button:hover{background:#f5f5f5}
    @media print{
      body{background:#fff;padding:0}
      .card{box-shadow:none;margin:0;border-radius:0}
      .actions,.linkbox{display:none!important}
      .receipt-content{margin-bottom:0}
    }
    @media (max-width:600px){
      .actions{flex-direction:column}
      .btn{width:100%;margin:2px 0}
    }
  </style>
</head>
<body>
  <div class="card">
    <?php if ($r): ?>
      <div class="receipt-content" id="receiptContent">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <h2>Payment <?php echo h(ucfirst($status)); ?></h2>
            <div class="meta">Transaction ID: <strong><?php echo h($r['id']); ?></strong></div>
          </div>
          <div class="amount">₹<?php echo number_format($r['amount'],2); ?></div>
        </div>

        <hr style="margin:14px 0">
        <p><strong>From:</strong> <?php echo h($r['from']); ?></p>
        <p><strong>To:</strong> <?php echo h($r['to']); ?></p>
        <p><strong>Note:</strong> <?php echo h($r['note'] ?: '—'); ?></p>
        <p class="meta">Date: <?php echo h($r['created_at']); ?></p>
        <p class="meta">Status: <strong style="color:#0b8043">Completed</strong></p>
      </div>

      <div style="margin-top:12px">
        <div class="linkbox">Shareable link: <a id="shareLink" href="<?php echo h($selfUrl); ?>"><?php echo h($selfUrl); ?></a></div>
      </div>

      <div class="actions">
        <button class="btn secondary" id="copyBtn">Copy Link</button>
        <button class="btn secondary" id="shareBtn">Share</button>
        <button class="btn secondary" id="downloadBtn">Download</button>
        <button class="btn secondary" id="printBtn">Print</button>
        <a class="btn primary" href="homepage_v1.php?refresh_balance=1">Back to Dashboard</a>
      </div>

    <?php else: ?>
      <h2 class="failed">Payment <?php echo h(ucfirst($status)); ?></h2>
      <?php if ($error): ?><p class="meta">Error: <?php echo h($error); ?></p><?php endif; ?>
      <p class="meta">No shareable receipt is available.</p>
      <div class="actions">
        <a class="btn primary" href="phone.html">Try Again</a>
        <a class="btn secondary" href="homepage_v1.php?refresh_balance=1">Back to Dashboard</a>
      </div>
    <?php endif; ?>
  </div>

  <script>
    document.getElementById('copyBtn')?.addEventListener('click', function(){
      const link = document.getElementById('shareLink')?.href || window.location.href;
      navigator.clipboard.writeText(link).then(()=> alert('Link copied to clipboard'));
    });
    
    document.getElementById('shareBtn')?.addEventListener('click', async function(){
      const link = document.getElementById('shareLink')?.href || window.location.href;
      if (navigator.share) { 
        try { 
          await navigator.share({ 
            title: 'Payment Receipt', 
            text: 'Payment completed successfully. Transaction ID: <?php echo h($r['id'] ?? ''); ?>', 
            url: link 
          }); 
        } catch(e){} 
      } else { 
        navigator.clipboard.writeText(link).then(()=> alert('Link copied to clipboard')); 
      }
    });
    
    document.getElementById('downloadBtn')?.addEventListener('click', function(){
      const options = document.createElement('div');
      options.className = 'download-options';
      options.style.display = 'block';
      options.style.left = this.offsetLeft + 'px';
      options.style.top = (this.offsetTop + this.offsetHeight + 5) + 'px';
      
      options.innerHTML = `
        <button onclick="downloadAsImage()">📷 Save as Image</button>
        <button onclick="downloadAsText()">📄 Save as Text</button>
        <button onclick="window.print()">🖨️ Print PDF</button>
      `;
      
      document.body.appendChild(options);
      
      // Close dropdown when clicking outside
      setTimeout(() => {
        document.addEventListener('click', function closeDropdown(e) {
          if (!options.contains(e.target) && e.target !== document.getElementById('downloadBtn')) {
            options.remove();
            document.removeEventListener('click', closeDropdown);
          }
        });
      }, 100);
    });
    
    document.getElementById('printBtn')?.addEventListener('click', function(){ window.print(); });
    
    function downloadAsImage() {
      // Create canvas from receipt content
      const content = document.getElementById('receiptContent');
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      
      // Set canvas size
      canvas.width = 400;
      canvas.height = 300;
      
      // White background
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      
      // Add receipt text
      ctx.fillStyle = '#000000';
      ctx.font = '16px Inter, Arial, sans-serif';
      
      let y = 30;
      ctx.font = 'bold 18px Inter, Arial, sans-serif';
      ctx.fillText('Payment Receipt', 20, y);
      
      y += 30;
      ctx.font = '14px Inter, Arial, sans-serif';
      ctx.fillText('Transaction ID: <?php echo h($r['id'] ?? ''); ?>', 20, y);
      
      y += 25;
      ctx.font = 'bold 24px Inter, Arial, sans-serif';
      ctx.fillStyle = '#0b8043';
      ctx.fillText('₹<?php echo number_format($r['amount'] ?? 0, 2); ?>', 20, y);
      
      y += 30;
      ctx.fillStyle = '#000000';
      ctx.font = '14px Inter, Arial, sans-serif';
      ctx.fillText('From: <?php echo h($r['from'] ?? ''); ?>', 20, y);
      
      y += 20;
      ctx.fillText('To: <?php echo h($r['to'] ?? ''); ?>', 20, y);
      
      y += 20;
      ctx.fillText('Note: <?php echo h($r['note'] ?: '—'); ?>', 20, y);
      
      y += 20;
      ctx.fillText('Date: <?php echo h($r['created_at'] ?? ''); ?>', 20, y);
      
      y += 20;
      ctx.fillStyle = '#0b8043';
      ctx.fillText('Status: Completed', 20, y);
      
      // Download
      const link = document.createElement('a');
      link.download = 'payment-receipt-<?php echo h($r['id'] ?? 'receipt'); ?>.png';
      link.href = canvas.toDataURL();
      link.click();
      
      document.querySelector('.download-options')?.remove();
    }
    
    function downloadAsText() {
      const receiptText = `Payment Receipt\n` +
        `================\n` +
        `Transaction ID: <?php echo h($r['id'] ?? ''); ?>\n` +
        `Amount: ₹<?php echo number_format($r['amount'] ?? 0, 2); ?>\n` +
        `From: <?php echo h($r['from'] ?? ''); ?>\n` +
        `To: <?php echo h($r['to'] ?? ''); ?>\n` +
        `Note: <?php echo h($r['note'] ?: '—'); ?>\n` +
        `Date: <?php echo h($r['created_at'] ?? ''); ?>\n` +
        `Status: Completed\n` +
        `\nThank you for using our payment service!`;
      
      const blob = new Blob([receiptText], { type: 'text/plain' });
      const link = document.createElement('a');
      link.download = 'payment-receipt-<?php echo h($r['id'] ?? 'receipt'); ?>.txt';
      link.href = URL.createObjectURL(blob);
      link.click();
      
      document.querySelector('.download-options')?.remove();
    }
  </script>
</body>
</html>
