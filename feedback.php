<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';

$role = '';
if (isset($_SESSION['user_id'])) {
    $currentUser = mfcRequireRoles($conn, MFC_USER_ROLES);
    $role = $currentUser['role'];
}

if (empty($_SESSION['feedback_csrf'])) {
    $_SESSION['feedback_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['message_csrf'])) {
    $_SESSION['message_csrf'] = bin2hex(random_bytes(32));
}
$feedbackFlash = $_SESSION['feedback_flash'] ?? null;
unset($_SESSION['feedback_flash']);

$feedback = null;
if (in_array($role, ['admin', 'service_manager'], true)) {
    $feedback = $conn->query("SELECT f.feedback_id, f.subject, f.message, f.rating, f.created_at, f.review_status, f.staff_reply, f.replied_at, f.resolved_at, u.full_name FROM feedback f LEFT JOIN users u ON u.user_id = f.user_id WHERE f.category = 'feedback' ORDER BY (f.review_status = 'new') DESC, f.created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Farmer Feedback | Meru Farmers Centre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main class="container py-4">
        <section class="mb-5" id="feedback-form">
            <div class="row g-4 align-items-start">
                <div class="col-lg-5"><p class="text-uppercase small text-success fw-bold">Farmer voice</p><h1 class="h2">Farmer Feedback &amp; Reviews</h1><p>Rate a service, product, or experience and tell us what would make it better.</p></div>
                <div class="col-lg-7">
                    <?php if ($feedbackFlash && $feedbackFlash['type'] === 'error'): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($feedbackFlash['message']); ?></div><?php endif; ?>
                    <form id="farmer-feedback-form" class="form-panel" action="feedback_handler.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['feedback_csrf']); ?>">
                        <div class="field-row"><label>Farmer / customer name<input type="text" name="name" maxlength="100" autocomplete="name" required></label></div>
                        <div class="field-row"><label>Email address<input type="email" name="email" maxlength="100" autocomplete="email" required></label></div>
                        <div class="field-row"><label>Phone (optional)<input type="tel" name="phone" maxlength="30" autocomplete="tel"></label></div>
                        <div class="field-row"><label>Service, product, or experience<select name="service_rated" required><option value="">Choose a category</option><option>Training &amp; workshops</option><option>Farm inputs / products</option><option>Service booking</option><option>Customer support</option><option>Overall experience</option><option>Other</option></select></label></div>
                        <div class="field-row"><label>Satisfaction rating<select name="rating" required><option value="">Select a rating</option><option value="5">5 - Excellent</option><option value="4">4 - Good</option><option value="3">3 - Fair</option><option value="2">2 - Poor</option><option value="1">1 - Very poor</option></select></label></div>
                        <div class="field-row"><label>Feedback or suggestions<textarea name="message" rows="5" maxlength="10000" required></textarea></label></div>
                        <button class="btn btn-success" type="submit">Send feedback</button>
                    </form>
                </div>
            </div>
        </section>

        <?php if ($feedbackFlash && $feedbackFlash['type'] === 'success'): ?>
            <dialog id="feedbackSuccess" aria-labelledby="feedbackSuccessTitle" style="max-width:480px;width:calc(100% - 32px);border:0;border-radius:12px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.24);">
                <h2 id="feedbackSuccessTitle">Feedback received</h2><p><?php echo htmlspecialchars($feedbackFlash['message']); ?></p>
                <div class="d-flex flex-wrap gap-2 justify-content-end"><button type="button" class="btn btn-outline-secondary" id="feedbackClose">Close</button><button type="button" class="btn btn-success" id="feedbackAgain">Send more feedback</button></div>
            </dialog>
            <script>const feedbackDialog=document.getElementById('feedbackSuccess');feedbackDialog.showModal();document.getElementById('feedbackClose').addEventListener('click',()=>feedbackDialog.close());document.getElementById('feedbackAgain').addEventListener('click',()=>{feedbackDialog.close();document.getElementById('farmer-feedback-form').reset();document.querySelector('#farmer-feedback-form [name="name"]').focus();});</script>
        <?php endif; ?>

        <?php if (in_array($role, ['admin', 'service_manager'], true)): ?>
            <section id="feedback-inbox">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><h2 class="h3 mb-0">Farmer Feedback Inbox</h2><a class="btn btn-outline-success" href="staff.php#messages">Staff workspace</a></div>
                <?php if (!$feedback || $feedback->num_rows === 0): ?><p>No farmer feedback entries yet.</p><?php else: ?>
                    <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Received</th><th>Farmer</th><th>Rating</th><th>Category</th><th>Feedback</th><th>Status / reply</th></tr></thead><tbody>
                        <?php while ($row = $feedback->fetch_assoc()): ?><tr>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td><td><?php echo htmlspecialchars($row['full_name'] ?? 'Guest'); ?></td><td><?php echo $row['rating'] ? ((int) $row['rating'] . ' / 5') : 'Not rated'; ?></td><td><?php echo htmlspecialchars($row['subject']); ?></td><td style="min-width:240px;white-space:pre-wrap"><?php echo htmlspecialchars($row['message']); ?></td>
                            <td><span class="badge text-bg-<?php echo $row['review_status'] === 'resolved' ? 'success' : ($row['review_status'] === 'reviewed' ? 'info' : 'warning'); ?>"><?php echo htmlspecialchars(ucfirst($row['review_status'])); ?></span><?php if (!empty($row['staff_reply'])): ?><p class="small mt-1 mb-0">Reply: <?php echo nl2br(htmlspecialchars($row['staff_reply'])); ?><br><?php echo htmlspecialchars($row['replied_at'] ?? ''); ?></p><?php endif; ?><form method="POST" action="message_handler.php" class="mt-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['message_csrf']); ?>"><input type="hidden" name="message_id" value="<?php echo (int) $row['feedback_id']; ?>"><input type="hidden" name="category" value="feedback"><textarea class="form-control form-control-sm mb-1" name="staff_reply" rows="2" maxlength="5000" placeholder="Reply (optional)"></textarea><select class="form-select form-select-sm mb-1" name="review_status"><option value="new" <?php echo $row['review_status'] === 'new' ? 'selected' : ''; ?>>New / unread</option><option value="reviewed" <?php echo $row['review_status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option><option value="resolved" <?php echo $row['review_status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option></select><button class="btn btn-sm btn-outline-success">Save reply / status</button></form></td>
                        </tr><?php endwhile; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>