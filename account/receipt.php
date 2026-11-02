<?php
session_start();

$id = $_GET['id'] ?? '';
if (!$id) {
    http_response_code(400);
    echo "<h2>Receipt ID missing</h2>";
    exit;
}

if (!isset($_SESSION['mock_receipts'][$id])) {
    http_response_code(404);
    echo "<h2>Receipt not found</h2><p>It may have expired or is invalid.</p>";
    exit;
}

$r = $_SESSION['mock_receipts'][$id];
// Basic HTML receipt page
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Payment Receipt - <?php echo htmlspecialchars($r['id']); ?></title>
  <style>
    body{font-family:Inter, Arial, sans-serif; background:#f6fbff; color:#123; padding:24px}
    .card{max-width:720px;margin:40px auto;background:#fff;border-radius:12px;padding:22px;box-shadow:0 12px 40px rgba(0,0,0,0.06)}
    .amount{font-size:36px;font-weight:700;color:#0b8043}
    .row{display:flex;justify-content:space-between;align-items:center}
    .meta{color:#666}
    .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}
    .btn{padding:10px 14px;border-radius:8px;border:0;cursor:pointer}
    .btn.primary{background:#1d9bf0;color:#fff}
    .btn.secondary{background:#eef6ff;color:#1d9bf0}
    a.share-link{word-break:break-all;color:#1d9bf0}
  </style>
</head>
<body>
  <div class="card">
    <div class="row">
      <div>
        <h2>Payment Receipt</h2>
        <div class="meta">Transaction ID: <strong><?php echo htmlspecialchars($r['id']); ?></strong></div>
      </div>
      <div class="amount">₹<?php echo number_format($r['amount'],2); ?></div>
    </div>

    <hr style="margin:14px 0">
    <p><strong>From:</strong> <?php echo htmlspecialchars($r['from']); ?></p>
    <p><strong>To:</strong> <?php echo htmlspecialchars($r['to']); ?></p>
    <p><strong>Note:</strong> <?php echo htmlspecialchars($r['note'] ?: '—'); ?></p>
    <p class="meta">Date: <?php echo htmlspecialchars($r['created_at']); ?></p>

    <div class="actions">
      <button class="btn secondary" id="copyBtn">Copy</button>
      <button class="btn secondary" id="shareBtn">Share</button>
      <button class="btn secondary" id="printBtn">Print</button>
      <a class="btn primary" href="homepage_v1.php">Back to Dashboard</a>
    </div>

    <p style="margin-top:14px;color:#666">Shareable link: <a class="share-link" id="shareLink" href="<?php echo htmlspecialchars($r['id'] ? 'receipt.php?id=' . urlencode($r['id']) : '#'); ?>"><?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/receipt.php?id=' . urlencode($r['id'])); ?></a></p>
  </div>

  <script>
    const shareLink = document.getElementById('shareLink').href;
    document.getElementById('copyBtn').addEventListener('click', ()=>{
      navigator.clipboard.writeText(shareLink).then(()=> alert('Link copied to clipboard'));
    });
    document.getElementById('shareBtn').addEventListener('click', async ()=>{
      if (navigator.share) {
        try { await navigator.share({ title: 'Payment Receipt', text: 'Payment Receipt: '+shareLink, url: shareLink }); } catch(e) {}
      } else {
        navigator.clipboard.writeText(shareLink).then(()=> alert('Link copied to clipboard'));
      }
    });
    document.getElementById('printBtn').addEventListener('click', ()=>{ window.print(); });
  </script>
</body>
</html>
