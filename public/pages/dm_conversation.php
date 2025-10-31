<?php
// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/pages/login.php');
    exit;
}

$current_user_id = $_SESSION['user']['user_id'];
$conversation_id = $_GET['id'] ?? 0;

if (!$conversation_id) {
    header('Location: ' . BASE_URL . '/pages/messages.php');
    exit;
}

// 대화방 정보 가져오기
$stmt = $pdo->prepare("
    SELECT c.*
    FROM dm_conversations c
    WHERE c.conversation_id = ?
    AND EXISTS (
        SELECT 1 FROM dm_participants 
        WHERE conversation_id = c.conversation_id 
        AND user_id = ?
        AND is_active = TRUE
    )
");
$stmt->execute([$conversation_id, $current_user_id]);
$conversation = $stmt->fetch();

if (!$conversation) {
    header('Location: ' . BASE_URL . '/pages/messages.php');
    exit;
}

// 참가자 정보 가져오기
$stmt = $pdo->prepare("
    SELECT u.user_id, u.username, u.nickname, u.profile_img
    FROM dm_participants p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.conversation_id = ? AND p.is_active = TRUE
");
$stmt->execute([$conversation_id]);
$participants = $stmt->fetchAll();

// 대화 상대 정보 (1:1 대화인 경우)
$other_user = null;
if (!$conversation['is_group']) {
    foreach ($participants as $p) {
        if ($p['user_id'] != $current_user_id) {
            $other_user = $p;
            break;
        }
    }
}

// 대화방 이름
$conversation_name = $conversation['group_name'];
if (!$conversation['is_group']) {
    if ($other_user) {
        $conversation_name = $other_user['nickname'];
    } else {
        // 자기 자신과의 대화
        $conversation_name = $_SESSION['user']['nickname'] . ' (나)';
    }
}

// 메시지 가져오기
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        u.username,
        u.nickname,
        u.profile_img,
        EXISTS (
            SELECT 1 FROM dm_read_status 
            WHERE message_id = m.message_id 
            AND user_id = ?
        ) as is_read_by_me
    FROM dm_messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$current_user_id, $conversation_id]);
$messages = $stmt->fetchAll();

// 읽음 처리
$stmt = $pdo->prepare("
    INSERT IGNORE INTO dm_read_status (message_id, user_id)
    SELECT m.message_id, ?
    FROM dm_messages m
    WHERE m.conversation_id = ?
    AND m.sender_id != ?
");
$stmt->execute([$current_user_id, $conversation_id, $current_user_id]);

function getRelativeTime($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return '방금 전';
    if ($diff < 3600) return floor($diff / 60) . '분 전';
    if ($diff < 86400) return floor($diff / 3600) . '시간 전';
    if ($diff < 604800) return floor($diff / 86400) . '일 전';
    return date('Y-m-d', $timestamp);
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="dm-conversation-container">
    <!-- 대화방 헤더 -->
    <div class="dm-conversation-header">
        <button class="btn-back" onclick="window.location.href='<?php echo BASE_URL; ?>/pages/messages.php'">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
        </button>
        
        <div class="conversation-info">
            <?php if ($other_user): ?>
                <img src="<?php echo getProfileImageUrl($other_user['profile_img']); ?>" alt="profile" class="conversation-avatar">
            <?php elseif ($conversation['is_group']): ?>
                <div class="conversation-avatar group-avatar">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
            <?php else: ?>
                <img src="<?php echo getProfileImageUrl($_SESSION['user']['profile_img']); ?>" alt="profile" class="conversation-avatar">
            <?php endif; ?>
            
            <div class="conversation-details">
                <h3><?php echo htmlspecialchars($conversation_name); ?></h3>
                <?php if ($conversation['is_group']): ?>
                    <p><?php echo count($participants); ?>명 참가 중</p>
                <?php elseif ($other_user): ?>
                    <p>@<?php echo htmlspecialchars($other_user['username']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="conversation-actions">
            <button class="btn-icon" onclick="toggleConversationMenu()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="1"></circle>
                    <circle cx="12" cy="5" r="1"></circle>
                    <circle cx="12" cy="19" r="1"></circle>
                </svg>
            </button>
        </div>
    </div>
    
    <!-- 메시지 영역 -->
    <div class="dm-messages-container" id="messagesContainer">
        <?php if (empty($messages)): ?>
            <div class="empty-messages">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <p>대화를 시작해보세요</p>
            </div>
        <?php else: ?>
            <?php 
            $prev_date = null;
            foreach ($messages as $msg): 
                $msg_date = date('Y-m-d', strtotime($msg['created_at']));
                if ($msg_date !== $prev_date):
                    $prev_date = $msg_date;
            ?>
                <div class="message-date-divider">
                    <?php 
                    $today = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    if ($msg_date === $today) {
                        echo '오늘';
                    } elseif ($msg_date === $yesterday) {
                        echo '어제';
                    } else {
                        echo date('Y년 n월 j일', strtotime($msg_date));
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="message-wrapper <?php echo $msg['sender_id'] == $current_user_id ? 'mine' : 'theirs'; ?>">
                <?php if ($msg['sender_id'] != $current_user_id): ?>
                    <img src="<?php echo getProfileImageUrl($msg['profile_img']); ?>" alt="profile" class="message-avatar">
                <?php endif; ?>
                
                <div class="message-content">
                    <?php if ($msg['sender_id'] != $current_user_id && $conversation['is_group']): ?>
                        <div class="message-sender"><?php echo htmlspecialchars($msg['nickname']); ?></div>
                    <?php endif; ?>
                    
                    <div class="message-bubble">
                        <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                    </div>
                    
                    <div class="message-time">
                        <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- 메시지 입력 -->
    <div class="dm-input-container">
        <form id="messageForm" onsubmit="sendMessage(event)">
            <textarea 
                id="messageInput" 
                placeholder="메시지 입력..." 
                rows="1"
                maxlength="2000"
                required
            ></textarea>
            <button type="submit" class="btn-send" id="sendBtn" disabled>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </form>
    </div>
</div>

<!-- 대화방 메뉴 -->
<div class="conversation-menu" id="conversationMenu" style="display: none;">
    <div class="menu-overlay" onclick="toggleConversationMenu()"></div>
    <div class="menu-content">
        <?php if ($conversation['is_group']): ?>
            <button class="menu-item" onclick="showParticipants()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                참가자 보기
            </button>
        <?php endif; ?>
        
        <button class="menu-item text-danger" onclick="leaveConversation()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            대화방 나가기
        </button>
    </div>
</div>

<style>
.dm-conversation-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 60px);
    max-width: 1000px;
    margin: 0 auto;
    background: var(--bg-primary);
}

.dm-conversation-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-primary);
    position: sticky;
    top: 0;
    z-index: 10;
}

.btn-back {
    background: none;
    border: none;
    color: var(--text-primary);
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s;
}

.btn-back:hover {
    background: var(--bg-hover);
}

.conversation-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 12px;
}

.conversation-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.group-avatar {
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
}

.conversation-details h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.conversation-details p {
    margin: 0;
    font-size: 13px;
    color: var(--text-secondary);
}

.dm-messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.empty-messages {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
}

.empty-messages svg {
    margin-bottom: 16px;
    opacity: 0.5;
}

.message-date-divider {
    text-align: center;
    margin: 20px 0;
    position: relative;
}

.message-date-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: var(--border-color);
    z-index: 0;
}

.message-date-divider::after {
    content: attr(data-date);
}

.message-date-divider {
    font-size: 13px;
    color: var(--text-secondary);
    background: var(--bg-primary);
    padding: 4px 12px;
    border-radius: 12px;
    display: inline-block;
    position: relative;
    z-index: 1;
}

.message-wrapper {
    display: flex;
    gap: 8px;
    max-width: 70%;
}

.message-wrapper.mine {
    flex-direction: row-reverse;
    margin-left: auto;
}

.message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.message-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.message-wrapper.mine .message-content {
    align-items: flex-end;
}

.message-sender {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.message-bubble {
    background: var(--bg-secondary);
    padding: 8px 12px;
    border-radius: 16px;
    word-wrap: break-word;
    white-space: pre-wrap;
    line-height: 1.4;
    font-size: 15px;
}


.message-wrapper.mine .message-bubble {
    background: var(--primary-color);
    color: white;
    border-radius: 16px;
}

.message-time {
    font-size: 11px;
    color: var(--text-secondary);
    padding: 0 4px;
}

.dm-input-container {
    padding: 16px 20px;
    border-top: 1px solid var(--border-color);
    background: var(--bg-primary);
}

.dm-input-container form {
    display: flex;
    gap: 12px;
    align-items: flex-end;
}

#messageInput {
    flex: 1;
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 10px 16px;
    font-size: 15px;
    resize: none;
    max-height: 120px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    outline: none;
    transition: all 0.2s;
}

#messageInput:focus {
    border-color: var(--primary-color);
}

.btn-send {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: var(--primary-color);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.btn-send:hover:not(:disabled) {
    background: #5568d3;
    transform: scale(1.05);
}

.btn-send:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.conversation-menu {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}

.menu-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.menu-content {
    position: absolute;
    top: 70px;
    right: 20px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
    min-width: 200px;
}

.menu-item {
    width: 100%;
    padding: 12px 16px;
    border: none;
    background: none;
    color: var(--text-primary);
    text-align: left;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: background 0.2s;
}

.menu-item:first-child {
    border-radius: 12px 12px 0 0;
}

.menu-item:last-child {
    border-radius: 0 0 12px 12px;
}

.menu-item:hover {
    background: var(--bg-hover);
}

.menu-item.text-danger {
    color: #dc3545;
}

@media (max-width: 768px) {
    .message-wrapper {
        max-width: 85%;
    }
}
</style>

<script>
const conversationId = <?php echo $conversation_id; ?>;

// 메시지 입력 활성화
document.getElementById('messageInput').addEventListener('input', function() {
    const btn = document.getElementById('sendBtn');
    btn.disabled = this.value.trim().length === 0;
});

// 자동 높이 조절
document.getElementById('messageInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
});

// 메시지 전송
function sendMessage(event) {
    event.preventDefault();
    
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    const formData = new FormData();
    formData.append('conversation_id', conversationId);
    formData.append('message_text', message);
    
    fetch('<?php echo BASE_URL; ?>/api/dm_send_message.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            input.style.height = 'auto';
            document.getElementById('sendBtn').disabled = true;
            location.reload(); // 실제로는 WebSocket 등으로 실시간 업데이트
        } else {
            alert(data.message || '메시지 전송에 실패했습니다.');
        }
    })
    .catch(err => {
        console.error('Send message error:', err);
        alert('오류가 발생했습니다.');
    });
}

// 대화방 메뉴
function toggleConversationMenu() {
    const menu = document.getElementById('conversationMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// 대화방 나가기
function leaveConversation() {
    if (!confirm('대화방을 나가시겠습니까?')) return;
    
    const formData = new FormData();
    formData.append('conversation_id', conversationId);
    
    fetch('<?php echo BASE_URL; ?>/api/dm_leave_conversation.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?php echo BASE_URL; ?>/pages/messages.php';
        } else {
            alert(data.message || '대화방 나가기에 실패했습니다.');
        }
    })
    .catch(err => {
        console.error('Leave conversation error:', err);
        alert('오류가 발생했습니다.');
    });
}

// 스크롤을 맨 아래로
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    container.scrollTop = container.scrollHeight;
}

// 페이지 로드 시 스크롤
window.addEventListener('load', scrollToBottom);

// Enter 키로 전송 (Shift+Enter는 줄바꿈)
document.getElementById('messageInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) {
            document.getElementById('messageForm').dispatchEvent(new Event('submit'));
        }
    }
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>