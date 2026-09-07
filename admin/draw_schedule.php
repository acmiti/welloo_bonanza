<?php
// admin/draw_schedule.php — the standalone Draw Schedule queue has been merged
// into Draw Batches, which is now the single source of truth for scheduling and
// batch management. Redirect any bookmarked/legacy links there.
header('Location: /admin/batches.php', true, 301);
exit;
