/**
 * Receipt Generation Utility
 * Uses html2canvas to capture receipt element and download/share as image
 */

// Load html2canvas dynamically if not present
if (typeof html2canvas === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://html2canvas.hertzen.com/dist/html2canvas.min.js';
    document.head.appendChild(script);
}

window.showReceipt = function (data) {
    // Remove existing receipt if any
    const existing = document.getElementById('receiptModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'receiptModal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:20000;display:flex;align-items:center;justify-content:center;font-family:Inter,sans-serif;';

    const timestamp = data.date || new Date().toLocaleString();
    const amount = parseFloat(data.amount).toFixed(2);
    const statusColor = data.success ? '#4CAF50' : '#F44336';
    const statusIcon = data.success ? '✓' : '✗';
    const statusText = data.success ? 'Payment Successful' : 'Payment Failed';

    modal.innerHTML = `
        <div class="receipt-container" style="background:white;width:380px;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.4);">
            <div id="receiptContent" style="padding:30px;background:white;position:relative;">
                <!-- Header -->
                <div style="text-align:center;margin-bottom:25px;">
                    <div style="width:60px;height:60px;background:${statusColor};border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:30px;margin:0 auto 15px;">${statusIcon}</div>
                    <h2 style="margin:0;color:#333;font-size:22px;">${statusText}</h2>
                    <div style="font-size:36px;font-weight:800;color:#333;margin-top:10px;">₹${amount}</div>
                </div>

                <!-- Details -->
                <div style="background:#f8f9fa;border-radius:12px;padding:20px;margin-bottom:20px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;">
                        <span style="color:#666;">To</span>
                        <span style="font-weight:600;color:#333;">${data.recipient || 'Merchant'}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;">
                        <span style="color:#666;">Transaction ID</span>
                        <span style="font-weight:600;color:#333;font-family:monospace;">${data.transaction_id || 'N/A'}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;">
                        <span style="color:#666;">Date</span>
                        <span style="font-weight:600;color:#333;">${timestamp}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:14px;border-top:1px dashed #ddd;padding-top:12px;margin-top:12px;">
                        <span style="color:#666;">Payment Mode</span>
                        <span style="font-weight:600;color:#333;">UPI</span>
                    </div>
                </div>

                <!-- Footer -->
                <div style="text-align:center;color:#999;font-size:12px;">
                    <i class="fas fa-lock"></i> Secured by PaySecure
                </div>
            </div>

            <!-- Actions -->
            <div style="padding:20px;background:#f0f2f5;display:flex;gap:10px;">
                <button onclick="downloadReceipt()" style="flex:1;padding:12px;background:#1976D2;color:white;border:none;border-radius:10px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="shareReceipt()" style="flex:1;padding:12px;background:#4CAF50;color:white;border:none;border-radius:10px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                    <i class="fas fa-share-alt"></i> Share
                </button>
                <button onclick="closeReceipt()" style="flex:1;padding:12px;background:white;color:#333;border:1px solid #ddd;border-radius:10px;font-weight:600;cursor:pointer;">
                    Close
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
}

window.ReceiptUtils = {
    downloadReceipt: function (elementId, filename) {
        const element = document.getElementById(elementId);
        if (!element) {
            console.error('Receipt element not found:', elementId);
            return;
        }

        // Find button to show loading state (optional)
        const btn = document.querySelector(`button[onclick*="downloadReceipt"]`);
        let originalText = '';
        if (btn) {
            originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            btn.disabled = true;
        }

        html2canvas(element, {
            scale: 2,
            backgroundColor: '#ffffff',
            logging: false,
            useCORS: true
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = filename || ('PaySecure_Receipt_' + Date.now() + '.png');
            link.href = canvas.toDataURL('image/png');
            link.click();

            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }).catch(err => {
            console.error('Download failed:', err);
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Failed to generate receipt image.');
            }
        });
    },

    shareReceipt: function (elementId, title) {
        const element = document.getElementById(elementId);
        if (!element) {
            console.error('Receipt element not found:', elementId);
            return;
        }

        const btn = document.querySelector(`button[onclick*="shareReceipt"]`);
        let originalText = '';
        if (btn) {
            originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';
            btn.disabled = true;
        }

        html2canvas(element, {
            scale: 2,
            backgroundColor: '#ffffff',
            logging: false,
            useCORS: true
        }).then(canvas => {
            canvas.toBlob(blob => {
                const file = new File([blob], 'PaySecure_Receipt.png', { type: 'image/png' });
                const shareData = {
                    title: title || 'PaySecure Receipt',
                    text: 'Transaction Receipt from PaySecure',
                    files: [file]
                };

                if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                    navigator.share(shareData)
                        .then(() => {
                            if (btn) {
                                btn.innerHTML = originalText;
                                btn.disabled = false;
                            }
                        })
                        .catch(err => {
                            console.error('Share failed:', err);
                            if (btn) {
                                btn.innerHTML = originalText;
                                btn.disabled = false;
                            }
                            if (err.name !== 'AbortError') {
                                alert('Sharing not supported. Receipt will be downloaded instead.');
                                ReceiptUtils.downloadReceipt(elementId, title);
                            }
                        });
                } else {
                    // Fallback to clipboard
                    if (btn) {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }

                    if (navigator.clipboard && navigator.clipboard.write) {
                        navigator.clipboard.write([
                            new ClipboardItem({ 'image/png': blob })
                        ]).then(() => {
                            alert('Receipt copied to clipboard!');
                        }).catch(() => {
                            ReceiptUtils.downloadReceipt(elementId, title);
                        });
                    } else {
                        ReceiptUtils.downloadReceipt(elementId, title);
                    }
                }
            }, 'image/png');
        }).catch(err => {
            console.error('Share generation failed:', err);
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Failed to generate receipt for sharing.');
            }
        });
    }
};

// Backward compatibility for phone.html
window.downloadReceipt = function () {
    ReceiptUtils.downloadReceipt('receiptContent');
}

window.shareReceipt = function () {
    ReceiptUtils.shareReceipt('receiptContent');
}

window.closeReceipt = function () {
    const modal = document.getElementById('receiptModal');
    if (modal) modal.remove();
    // Optional: reload page if needed
    if (window.location.href.includes('scan_and_pay') || window.location.href.includes('bill_pay')) {
        window.location.reload();
    }
}
