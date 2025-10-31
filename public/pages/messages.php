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

// 함수 정의 - 사용하기 전에 먼저 정의
if (!function_exists('getRelativeTime')) {
    function getRelativeTime($datetime) {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) return '방금 전';
        if ($diff < 3600) return floor($diff / 60) . '분 전';
        if ($diff < 86400) return floor($diff / 3600) . '시간 전';
        if ($diff < 604800) return floor($diff / 86400) . '일 전';
        if ($diff < 2592000) return floor($diff / 604800) . '주 전';
        if ($diff < 31536000) return floor($diff / 2592000) . '개월 전';
        return floor($diff / 31536000) . '년 전';
    }
}

if (!function_exists('getConversationName')) {
    function getConversationName($conversation, $current_user_id) {
        if ($conversation['is_group']) {
            return $conversation['group_name'] ?: '그룹 대화';
        }
        
        foreach ($conversation['participants'] as $participant) {
            if ($participant['user_id'] != $current_user_id) {
                return $participant['nickname'];
            }
        }
        // 자기 자신과의 대화인 경우
        return $_SESSION['user']['nickname'] . ' (나)';
    }
}

if (!function_exists('getConversationImage')) {
    function getConversationImage($conversation, $current_user_id) {
        if (!$conversation['is_group']) {
            foreach ($conversation['participants'] as $participant) {
                if ($participant['user_id'] != $current_user_id) {
                    return getProfileImageUrl($participant['profile_img']);
                }
            }
            // 자기 자신과의 대화인 경우
            return getProfileImageUrl($_SESSION['user']['profile_img']);
        }
        return null;
    }
}

$current_user_id = $_SESSION['user']['user_id'];

// 대화방 목록 가져오기 - is_active = TRUE인 것만
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        (SELECT COUNT(*) FROM dm_participants WHERE conversation_id = c.conversation_id AND is_active = TRUE) as participant_count,
        (SELECT message_text FROM dm_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM dm_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM dm_messages m 
         WHERE m.conversation_id = c.conversation_id 
         AND m.sender_id != ? 
         AND m.message_id NOT IN (
             SELECT message_id FROM dm_read_status WHERE user_id = ?
         )) as unread_count
    FROM dm_conversations c
    WHERE EXISTS (
        SELECT 1 FROM dm_participants 
        WHERE conversation_id = c.conversation_id 
        AND user_id = ? 
        AND is_active = TRUE
    )
    ORDER BY COALESCE(
        (SELECT created_at FROM dm_messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1),
        c.created_at
    ) DESC
");
$stmt->execute([$current_user_id, $current_user_id, $current_user_id]);
$conversations = $stmt->fetchAll();

// 각 대화방의 참가자 정보 가져오기
foreach ($conversations as &$conv) {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.nickname, u.profile_img
        FROM dm_participants p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.conversation_id = ? AND p.is_active = TRUE
    ");
    $stmt->execute([$conv['conversation_id']]);
    $conv['participants'] = $stmt->fetchAll();
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="dm-container">
    <!-- 대화방 목록 사이드바 -->
    <div class="dm-sidebar">
        <div class="dm-sidebar-header">
            <h2>메시지</h2>
            <button class="btn-icon" onclick="openNewMessageModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12h18M12 3v18"></path>
                </svg>
            </button>
        </div>
        
        <!-- 검색창 -->
        <div class="dm-search-box">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" id="conversationSearch" placeholder="메시지 검색" class="dm-search-input">
        </div>
        
        <!-- 대화방 목록 -->
        <div class="dm-list" id="conversationList">
            <?php if (empty($conversations)): ?>
                <div class="dm-empty">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <p>메시지가 없습니다</p>
                    <small>새 대화를 시작해보세요</small>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <?php
                    $conv_name = getConversationName($conv, $current_user_id);
                    $conv_img = getConversationImage($conv, $current_user_id);
                    ?>
                    <div class="dm-item" 
                         data-conversation-id="<?php echo $conv['conversation_id']; ?>"
                         data-conversation-name="<?php echo htmlspecialchars($conv_name); ?>"
                         data-is-group="<?php echo $conv['is_group'] ? 'true' : 'false'; ?>"
                         data-conversation-img="<?php echo htmlspecialchars($conv_img ?? ''); ?>"
                         onclick="loadConversation(<?php echo $conv['conversation_id']; ?>, this)">
                        <div class="dm-item-avatar">
                            <?php if ($conv['is_group']): ?>
                                <div class="group-avatar">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                            <?php else: ?>
                                <img src="<?php echo $conv_img ?: (ASSETS_URL . '/images/sample.png'); ?>" alt="profile" onerror="this.src='<?php echo ASSETS_URL; ?>/images/sample.png'">
                            <?php endif; ?>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="unread-dot"></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="dm-item-content">
                            <div class="dm-item-header">
                                <h4><?php echo htmlspecialchars($conv_name); ?></h4>
                                <?php if ($conv['last_message_time']): ?>
                                    <span class="dm-time"><?php echo getRelativeTime($conv['last_message_time']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="dm-item-message">
                                <?php if ($conv['last_message']): ?>
                                    <p><?php echo htmlspecialchars(mb_substr($conv['last_message'], 0, 50)); ?></p>
                                <?php else: ?>
                                    <p class="text-muted">새 대화</p>
                                <?php endif; ?>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 대화 영역 -->
    <div class="dm-main" id="dmMain">
        <div class="dm-welcome">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <h3>메시지를 선택하세요</h3>
            <p>대화를 시작하려면 왼쪽에서 대화방을 선택하거나<br>새 메시지를 작성하세요</p>
            <button class="btn btn-primary mt-3" onclick="openNewMessageModal()">새 메시지</button>
        </div>
    </div>
</div>

<!-- 새 메시지 모달 -->
<div class="modal-overlay" id="newMessageModal">
    <div class="modal-content dm-modal">
        <div class="modal-header">
            <h3>새 메시지</h3>
            <button class="modal-close-btn" onclick="closeNewMessageModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        
        <div class="modal-body">
            <!-- 그룹 DM 옵션 -->
            <div class="group-option-container">
                <label class="checkbox-label">
                    <input type="checkbox" id="isGroupChat" onchange="toggleGroupOption()">
                    <span class="checkbox-text">그룹 대화 만들기</span>
                </label>
            </div>
            
            <!-- 그룹 이름 입력 -->
            <div id="groupNameInput" class="group-name-input" style="display: none;">
                <input type="text" class="form-control" placeholder="그룹 이름 (선택사항)" id="groupName" maxlength="100">
            </div>
            
            <!-- 사용자 검색 -->
            <div class="user-search-container">
                <div class="user-search-box">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <input type="text" id="userSearch" class="user-search-input" placeholder="사용자 이름 또는 아이디 검색..." autocomplete="off">
                </div>
                <div class="search-hint">최소 1자 이상 입력하세요</div>
            </div>
            
            <!-- 선택된 사용자 -->
            <div id="selectedUsers" class="selected-users"></div>
            
            <!-- 검색 결과 -->
            <div id="userSearchResults" class="user-search-results"></div>
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeNewMessageModal()">취소</button>
            <button class="btn btn-primary" id="startChatBtn" onclick="startConversation()" disabled>시작하기</button>
        </div>
    </div>
</div>


<style>
.dm-container {
    display: flex;
    height: calc(100vh - 60px);
    max-width: 1400px;
    margin: 0 auto;
    background: var(--bg-primary);
    border-left: 1px solid var(--border-color);
    border-right: 1px solid var(--border-color);
}

.dm-sidebar {
    width: 100%;
    max-width: 400px;
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    background: var(--bg-primary);
}

.dm-sidebar-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dm-sidebar-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: transparent;
    border: none;
    color: var(--primary-color);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.btn-icon:hover {
    background: var(--bg-hover);
}

.dm-search-box {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-secondary);
}

.dm-search-box svg {
    color: var(--text-secondary);
    flex-shrink: 0;
}

.dm-search-input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: 15px;
    color: var(--text-primary);
}

.dm-list {
    flex: 1;
    overflow-y: auto;
}

.dm-item {
    display: flex;
    gap: 12px;
    padding: 16px 20px;
    cursor: pointer;
    transition: background 0.2s;
    border-bottom: 1px solid var(--border-color);
}

.dm-item:hover {
    background: var(--bg-hover);
}

.dm-item.active {
    background: var(--bg-hover);
    border-right: 3px solid var(--primary-color);
}

.dm-item-avatar {
    position: relative;
    flex-shrink: 0;
}

.dm-item-avatar img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}

.group-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
}

.unread-dot {
    position: absolute;
    top: 0;
    right: 0;
    width: 12px;
    height: 12px;
    background: var(--primary-color);
    border: 2px solid var(--bg-primary);
    border-radius: 50%;
}

.dm-item-content {
    flex: 1;
    min-width: 0;
}

.dm-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.dm-item-header h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dm-time {
    font-size: 13px;
    color: var(--text-secondary);
    flex-shrink: 0;
    margin-left: 8px;
}

.dm-item-message {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dm-item-message p {
    margin: 0;
    font-size: 14px;
    color: var(--text-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unread-badge {
    background: var(--primary-color);
    color: white;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
    margin-left: 8px;
}

.dm-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--bg-primary);
}

.dm-welcome {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.dm-welcome svg {
    margin-bottom: 20px;
    opacity: 0.5;
}

.dm-welcome h3 {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 12px;
}

.dm-welcome p {
    font-size: 15px;
    line-height: 1.6;
}

.dm-empty {
    padding: 60px 20px;
    text-align: center;
    color: var(--text-secondary);
}

.dm-empty svg {
    margin-bottom: 20px;
    opacity: 0.5;
}

.dm-empty p {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.dm-empty small {
    font-size: 14px;
}

/* 대화 영역 스타일 */
.dm-conversation-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-primary);
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

.conversation-avatar.group-avatar {
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
    font-size: 13px;
    color: var(--text-secondary);
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
}

.message-date-divider span {
    background: var(--bg-primary);
    padding: 4px 12px;
    border-radius: 12px;
    position: relative;
    z-index: 1;
}

.message-wrapper {
    display: flex;
    gap: 8px;
    max-width: 70%;
    margin-bottom: 4px;
}

.message-wrapper.continuous {
    margin-top: 2px;
}

.message-wrapper:not(.continuous) {
    margin-top: 12px;
}

.message-wrapper.mine {
    flex-direction: row-reverse;
    margin-left: auto;
}

.message-avatar-spacer {
    width: 32px;
    flex-shrink: 0;
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
}

.message-bubble {
    background: var(--bg-secondary);
    padding: 10px 14px;
    border-radius: 18px;
    word-wrap: break-word;
    white-space: pre-wrap;
    max-width: 100%;
}

.message-wrapper.mine .message-bubble {
    background: var(--primary-color);
    color: white;
}

.message-wrapper.continuous .message-bubble {
    margin-top: 0;
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

/* 모달 스타일 */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
    animation: fadeIn 0.2s ease-out;
}

.modal-overlay.show {
    display: flex;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.dm-modal {
    width: 100%;
    max-width: 600px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    background: var(--bg-primary);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideUp 0.3s ease-out;
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color);
    flex-shrink: 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
}

.modal-close-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.modal-close-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
}

.group-option-container {
    margin-bottom: 20px;
    padding: 16px;
    background: var(--bg-secondary);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    user-select: none;
}

.checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--primary-color);
}

.checkbox-text {
    font-size: 15px;
    font-weight: 500;
    color: var(--text-primary);
}

.group-name-input {
    margin-bottom: 20px;
    animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.group-name-input .form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 15px;
    outline: none;
    transition: all 0.2s;
}

.group-name-input .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.user-search-container {
    margin-bottom: 20px;
}

.user-search-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-secondary);
    transition: all 0.2s;
}

.user-search-box:focus-within {
    border-color: var(--primary-color);
    background: var(--bg-primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.user-search-box svg {
    color: var(--text-secondary);
    flex-shrink: 0;
}

.user-search-input {
    flex: 1;
    border: none;
    background: transparent;
    color: var(--text-primary);
    font-size: 15px;
    outline: none;
}

.user-search-input::placeholder {
    color: var(--text-secondary);
}

.search-hint {
    margin-top: 8px;
    font-size: 13px;
    color: var(--text-secondary);
    padding-left: 4px;
}

.selected-users {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
    min-height: 0;
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: 12px;
    border: 1px dashed var(--border-color);
}

.selected-users:empty {
    display: none;
}

.selected-user-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--primary-color);
    color: white;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    animation: chipAppear 0.2s ease-out;
}

@keyframes chipAppear {
    from {
        opacity: 0;
        transform: scale(0.8);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.selected-user-chip img {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.selected-user-chip button {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 14px;
    line-height: 1;
    transition: background 0.2s;
}

.selected-user-chip button:hover {
    background: rgba(255, 255, 255, 0.3);
}

.user-search-results {
    max-height: 350px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-secondary);
}

.user-search-results:empty {
    display: none;
}

.user-result-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    cursor: pointer;
    transition: all 0.2s;
    border-bottom: 1px solid var(--border-color);
}

.user-result-item:last-child {
    border-bottom: none;
}

.user-result-item:hover:not(.disabled) {
    background: var(--bg-hover);
}

.user-result-item.selected {
    background: rgba(102, 126, 234, 0.1);
    border-left: 3px solid var(--primary-color);
}

.user-result-item img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.user-result-info {
    flex: 1;
    min-width: 0;
}

.user-result-info h5 {
    margin: 0 0 4px 0;
    font-size: 15px;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-result-info p {
    margin: 0;
    font-size: 14px;
    color: var(--text-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-result-badge {
    font-size: 12px;
    color: white;
    background: #6c757d;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 500;
    flex-shrink: 0;
}

.user-result-checkmark {
    color: var(--primary-color);
    font-size: 20px;
    flex-shrink: 0;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    flex-shrink: 0;
    background: var(--bg-secondary);
    border-radius: 0 0 16px 16px;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.btn-secondary {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: var(--border-color);
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #5568d3;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .dm-container {
        height: calc(100vh - 60px);
    }
    
    .dm-sidebar {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        z-index: 10;
        transition: transform 0.3s;
    }
    
    .dm-sidebar.hidden {
        transform: translateX(-100%);
    }
    
    .dm-main {
        width: 100%;
    }
    
    .message-wrapper {
        max-width: 85%;
    }
}
</style>

<script>
let selectedUsers = [];
let searchTimeout;
let currentConversationId = null;
let messagePollingInterval = null;

function openNewMessageModal() {
    document.getElementById('newMessageModal').classList.add('show');
    document.getElementById('userSearch').focus();
}

function closeNewMessageModal() {
    document.getElementById('newMessageModal').classList.remove('show');
    selectedUsers = [];
    document.getElementById('selectedUsers').innerHTML = '';
    document.getElementById('userSearchResults').innerHTML = '';
    document.getElementById('userSearch').value = '';
    document.getElementById('isGroupChat').checked = false;
    document.getElementById('groupNameInput').style.display = 'none';
    document.getElementById('groupName').value = '';
    updateStartButton();
}

function toggleGroupOption() {
    const isGroup = document.getElementById('isGroupChat').checked;
    const groupNameInput = document.getElementById('groupNameInput');
    
    if (isGroup) {
        groupNameInput.style.display = 'block';
    } else {
        groupNameInput.style.display = 'none';
        if (selectedUsers.length > 1) {
            selectedUsers = [selectedUsers[0]];
            updateSelectedUsers();
        }
    }
}

document.getElementById('userSearch')?.addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();
    
    if (query.length < 1) {
        document.getElementById('userSearchResults').innerHTML = '';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        searchUsers(query);
    }, 300);
});

function searchUsers(query) {
    fetch(`<?php echo BASE_URL; ?>/api/dm_search_users.php?q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displaySearchResults(data.users);
            } else {
                document.getElementById('userSearchResults').innerHTML = 
                    '<div style="padding: 24px; text-align: center; color: #e74c3c;">오류: ' + (data.message || '검색 실패') + '</div>';
            }
        })
        .catch(err => {
            console.error('Search error:', err);
            document.getElementById('userSearchResults').innerHTML = 
                '<div style="padding: 24px; text-align: center; color: #e74c3c;">검색 중 오류가 발생했습니다.</div>';
        });
}

function displaySearchResults(users) {
    const container = document.getElementById('userSearchResults');
    const isGroup = document.getElementById('isGroupChat').checked;
    
    if (users.length === 0) {
        container.innerHTML = '<div style="padding: 24px; text-align: center; color: var(--text-secondary);">검색 결과가 없습니다</div>';
        return;
    }
    
    container.innerHTML = users.map(user => {
        const isSelected = selectedUsers.some(u => u.user_id === user.user_id);
        const canSendDM = user.can_send_dm;
        const isDisabled = !canSendDM && !isGroup;
        const isSelf = user.is_self || false;
        
        let badgeText = '';
        let titleText = '';
        
        if (isSelf) {
            badgeText = '<span class="user-result-badge" style="background: #667eea;">나</span>';
            titleText = '나에게 메모 보내기';
        } else if (!canSendDM && !isGroup) {
            badgeText = '<span class="user-result-badge">팔로워만</span>';
            titleText = '팔로워만 DM을 보낼 수 있습니다';
        }
        
        return `
            <div class="user-result-item ${isSelected ? 'selected' : ''} ${isDisabled ? 'disabled' : ''}" 
                 onclick="${!isDisabled ? `toggleUserSelection(${JSON.stringify(user).replace(/"/g, '&quot;')})` : ''}"
                 title="${titleText}">
                <img src="${user.profile_img}" alt="profile">
                <div class="user-result-info">
                    <h5>${escapeHtml(user.nickname)}</h5>
                    <p>@${escapeHtml(user.username)}</p>
                </div>
                ${badgeText}
                ${isSelected ? '<span class="user-result-checkmark">✓</span>' : ''}
            </div>
        `;
    }).join('');
}

function toggleUserSelection(user) {
    const index = selectedUsers.findIndex(u => u.user_id === user.user_id);
    const isGroup = document.getElementById('isGroupChat').checked;
    
    if (index > -1) {
        selectedUsers.splice(index, 1);
    } else {
        if (!isGroup && selectedUsers.length >= 1) {
            selectedUsers = [user];
        } else {
            selectedUsers.push(user);
        }
    }
    
    updateSelectedUsers();
    updateStartButton();
    
    const query = document.getElementById('userSearch').value;
    if (query) {
        searchUsers(query);
    }
}

function updateSelectedUsers() {
    const container = document.getElementById('selectedUsers');
    
    if (selectedUsers.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    container.innerHTML = selectedUsers.map(user => `
        <div class="selected-user-chip">
            <img src="${user.profile_img}" alt="profile">
            <span>${escapeHtml(user.nickname)}</span>
            <button onclick="removeUser(${user.user_id}); event.stopPropagation();">×</button>
        </div>
    `).join('');
}

function removeUser(userId) {
    selectedUsers = selectedUsers.filter(u => u.user_id !== userId);
    updateSelectedUsers();
    updateStartButton();
    
    const query = document.getElementById('userSearch').value;
    if (query) {
        searchUsers(query);
    }
}

function updateStartButton() {
    const btn = document.getElementById('startChatBtn');
    btn.disabled = selectedUsers.length === 0;
}

function startConversation() {
    const isGroup = document.getElementById('isGroupChat').checked;
    const groupName = document.getElementById('groupName').value.trim();
    
    const formData = new FormData();
    formData.append('is_group', isGroup ? '1' : '0');
    formData.append('group_name', groupName);
    formData.append('user_ids', JSON.stringify(selectedUsers.map(u => u.user_id)));
    
    fetch('<?php echo BASE_URL; ?>/api/dm_create_conversation.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeNewMessageModal();
            // 새로 생성된 대화방 로드
            setTimeout(() => {
                const item = document.querySelector(`[data-conversation-id="${data.conversation_id}"]`);
                if (item) {
                    loadConversation(data.conversation_id, item);
                } else {
                    location.reload();
                }
            }, 500);
        } else {
            alert(data.message || '대화방 생성에 실패했습니다.');
        }
    })
    .catch(err => {
        console.error('Create conversation error:', err);
        alert('오류가 발생했습니다.');
    });
}

function loadConversation(conversationId, element) {
    console.log('Loading conversation:', conversationId);
    
    // 이전 선택 제거
    document.querySelectorAll('.dm-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // 현재 선택 표시
    if (element) {
        element.classList.add('active');
    }
    
    currentConversationId = conversationId;
    
    // 메시지 로드
    fetch(`<?php echo BASE_URL; ?>/api/dm_get_messages.php?conversation_id=${conversationId}`)
        .then(res => {
            console.log('Response status:', res.status);
            return res.text();
        })
        .then(text => {
            console.log('Response text:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    displayConversation(data);
                    
                    // 폴링 시작
                    startMessagePolling();
                } else {
                    console.error('API Error:', data);
                    alert(data.message || '메시지를 불러올 수 없습니다.');
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                console.error('Raw response:', text);
                alert('서버 응답 오류: ' + text.substring(0, 100));
            }
        })
        .catch(err => {
            console.error('Load conversation error:', err);
            alert('네트워크 오류가 발생했습니다: ' + err.message);
        });
}

function displayConversation(data) {
    const mainArea = document.getElementById('dmMain');
    
    const conversationName = data.conversation_name || '대화';
    const conversationImg = data.conversation_img || '<?php echo ASSETS_URL; ?>/images/sample.png';
    const isGroup = data.is_group;
    const messages = data.messages || [];
    
    let messagesHtml = '';
    if (messages.length === 0) {
        messagesHtml = `
            <div class="empty-messages">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <p>대화를 시작해보세요</p>
            </div>
        `;
    } else {
        let prevDate = null;
        let prevSender = null;
        let prevTime = null;
        
        messages.forEach((msg, index) => {
            const msgDate = msg.created_at.split(' ')[0];
            const msgTime = msg.created_at.split(' ')[1];
            const msgMinute = msgTime.substring(0, 5);
            
            // 날짜 구분선
            if (msgDate !== prevDate) {
                prevDate = msgDate;
                const today = new Date().toISOString().split('T')[0];
                const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
                let dateLabel = msgDate;
                if (msgDate === today) dateLabel = '오늘';
                else if (msgDate === yesterday) dateLabel = '어제';
                else {
                    const date = new Date(msgDate);
                    dateLabel = `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`;
                }
                
                messagesHtml += `<div class="message-date-divider"><span>${dateLabel}</span></div>`;
            }
            
            const isMine = msg.sender_id == <?php echo $current_user_id; ?>;
            const time = msgMinute;
            
            // 같은 사람이 1분 이내에 연속으로 보낸 메시지인지 확인
            const isContinuous = prevSender === msg.sender_id && 
                                 prevTime === msgMinute &&
                                 index > 0;
            
            if (isContinuous) {
                // 연속 메시지 - 프로필 사진과 이름 생략
                messagesHtml += `
                    <div class="message-wrapper ${isMine ? 'mine' : 'theirs'} continuous">
                        <div class="message-avatar-spacer"></div>
                        <div class="message-content">
                            <div class="message-bubble">${escapeHtml(msg.message_text).replace(/\n/g, '<br>')}</div>
                        </div>
                    </div>
                `;
            } else {
                // 새로운 메시지 그룹 시작
                messagesHtml += `
                    <div class="message-wrapper ${isMine ? 'mine' : 'theirs'}">
                        ${!isMine ? `<img src="${msg.profile_img}" alt="profile" class="message-avatar">` : ''}
                        <div class="message-content">
                            ${!isMine && isGroup ? `<div class="message-sender">${escapeHtml(msg.nickname)}</div>` : ''}
                            <div class="message-bubble">${escapeHtml(msg.message_text).replace(/\n/g, '<br>')}</div>
                            <div class="message-time">${time}</div>
                        </div>
                    </div>
                `;
            }
            
            prevSender = msg.sender_id;
            prevTime = msgMinute;
        });
    }
    
    mainArea.innerHTML = `
        <div class="dm-conversation-header">
            <div class="conversation-info">
                ${isGroup ? `
                    <div class="conversation-avatar group-avatar">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                ` : `
                    <img src="${conversationImg}" alt="profile" class="conversation-avatar" onerror="this.src='<?php echo ASSETS_URL; ?>/images/sample.png'">
                `}
                <div class="conversation-details">
                    <h3>${escapeHtml(conversationName)}</h3>
                    ${isGroup ? `<p>${data.participant_count}명 참가 중</p>` : ''}
                </div>
            </div>
            <button class="btn-icon" onclick="leaveConversation()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </button>
        </div>
        
        <div class="dm-messages-container" id="messagesContainer">
            ${messagesHtml}
        </div>
        
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
    `;
    
    // 이벤트 리스너 재설정
    setupMessageInput();
    scrollToBottom();
}

function setupMessageInput() {
    const input = document.getElementById('messageInput');
    const btn = document.getElementById('sendBtn');
    
    if (!input || !btn) return;
    
    input.addEventListener('input', function() {
        btn.disabled = this.value.trim().length === 0;
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (this.value.trim()) {
                document.getElementById('messageForm').dispatchEvent(new Event('submit', { cancelable: true }));
            }
        }
    });
}

function sendMessage(event) {
    event.preventDefault();
    
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message || !currentConversationId) return;
    
    const formData = new FormData();
    formData.append('conversation_id', currentConversationId);
    formData.append('message_text', message);
    
    // 버튼 비활성화
    const sendBtn = document.getElementById('sendBtn');
    sendBtn.disabled = true;
    
    fetch('<?php echo BASE_URL; ?>/api/dm_send_message.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            input.style.height = 'auto';
            
            // 메시지 목록만 업데이트 (입력창 초기화 유지)
            fetch(`<?php echo BASE_URL; ?>/api/dm_get_messages.php?conversation_id=${currentConversationId}`)
                .then(res => res.json())
                .then(msgData => {
                    if (msgData.success) {
                        updateMessagesOnly(msgData);
                        scrollToBottom();
                    }
                })
                .catch(err => console.error('Reload messages error:', err));
        } else {
            alert(data.message || '메시지 전송에 실패했습니다.');
            sendBtn.disabled = false;
        }
    })
    .catch(err => {
        console.error('Send message error:', err);
        alert('오류가 발생했습니다.');
        sendBtn.disabled = false;
    });
}

function startMessagePolling() {
    // 기존 폴링 중지
    if (messagePollingInterval) {
        clearInterval(messagePollingInterval);
    }
    
    // 5초마다 새 메시지 확인
    messagePollingInterval = setInterval(() => {
        if (currentConversationId) {
            // 입력 중인 텍스트 저장
            const inputElement = document.getElementById('messageInput');
            const savedText = inputElement ? inputElement.value : '';
            const savedHeight = inputElement ? inputElement.style.height : '';
            
            fetch(`<?php echo BASE_URL; ?>/api/dm_get_messages.php?conversation_id=${currentConversationId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('messagesContainer');
                        if (!container) return;
                        
                        const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
                        
                        // 메시지만 업데이트 (입력창은 유지)
                        updateMessagesOnly(data);
                        
                        // 입력 중이던 텍스트 복원
                        const newInput = document.getElementById('messageInput');
                        if (newInput && savedText) {
                            newInput.value = savedText;
                            newInput.style.height = savedHeight;
                            document.getElementById('sendBtn').disabled = savedText.trim().length === 0;
                        }
                        
                        if (wasAtBottom) {
                            scrollToBottom();
                        }
                    }
                })
                .catch(err => console.error('Polling error:', err));
        }
    }, 5000);
}

// 메시지 영역만 업데이트하는 새 함수
function updateMessagesOnly(data) {
    const messages = data.messages || [];
    const isGroup = data.is_group;
    
    let messagesHtml = '';
    if (messages.length === 0) {
        messagesHtml = `
            <div class="empty-messages">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <p>대화를 시작해보세요</p>
            </div>
        `;
    } else {
        let prevDate = null;
        let prevSender = null;
        let prevTime = null;
        
        messages.forEach((msg, index) => {
            const msgDate = msg.created_at.split(' ')[0];
            const msgTime = msg.created_at.split(' ')[1];
            const msgMinute = msgTime.substring(0, 5);
            
            if (msgDate !== prevDate) {
                prevDate = msgDate;
                const today = new Date().toISOString().split('T')[0];
                const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
                let dateLabel = msgDate;
                if (msgDate === today) dateLabel = '오늘';
                else if (msgDate === yesterday) dateLabel = '어제';
                else {
                    const date = new Date(msgDate);
                    dateLabel = `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일`;
                }
                
                messagesHtml += `<div class="message-date-divider"><span>${dateLabel}</span></div>`;
            }
            
            const isMine = msg.sender_id == <?php echo $current_user_id; ?>;
            const time = msgMinute;
            
            const isContinuous = prevSender === msg.sender_id && 
                                 prevTime === msgMinute &&
                                 index > 0;
            
            if (isContinuous) {
                messagesHtml += `
                    <div class="message-wrapper ${isMine ? 'mine' : 'theirs'} continuous">
                        <div class="message-avatar-spacer"></div>
                        <div class="message-content">
                            <div class="message-bubble">${escapeHtml(msg.message_text).replace(/\n/g, '<br>')}</div>
                        </div>
                    </div>
                `;
            } else {
                messagesHtml += `
                    <div class="message-wrapper ${isMine ? 'mine' : 'theirs'}">
                        ${!isMine ? `<img src="${msg.profile_img}" alt="profile" class="message-avatar">` : ''}
                        <div class="message-content">
                            ${!isMine && isGroup ? `<div class="message-sender">${escapeHtml(msg.nickname)}</div>` : ''}
                            <div class="message-bubble">${escapeHtml(msg.message_text).replace(/\n/g, '<br>')}</div>
                            <div class="message-time">${time}</div>
                        </div>
                    </div>
                `;
            }
            
            prevSender = msg.sender_id;
            prevTime = msgMinute;
        });
    }
    
    const container = document.getElementById('messagesContainer');
    if (container) {
        container.innerHTML = messagesHtml;
    }
}

function leaveConversation() {
    if (!currentConversationId) return;
    if (!confirm('대화방을 나가시겠습니까?')) return;
    
    const formData = new FormData();
    formData.append('conversation_id', currentConversationId);
    
    fetch('<?php echo BASE_URL; ?>/api/dm_leave_conversation.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || '대화방 나가기에 실패했습니다.');
        }
    })
    .catch(err => {
        console.error('Leave conversation error:', err);
        alert('오류가 발생했습니다.');
    });
}

function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (container) {
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 100);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 대화방 검색
document.getElementById('conversationSearch')?.addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    const items = document.querySelectorAll('.dm-item');
    
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(query) ? 'flex' : 'none';
    });
});

// ESC 키로 모달 닫기
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('newMessageModal');
        if (modal && modal.classList.contains('show')) {
            closeNewMessageModal();
        }
    }
});

// 모달 외부 클릭시 닫기
document.getElementById('newMessageModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeNewMessageModal();
    }
});

// 페이지 종료 시 폴링 중지
window.addEventListener('beforeunload', function() {
    if (messagePollingInterval) {
        clearInterval(messagePollingInterval);
    }
});
</script>
<?php require_once INCLUDES_PATH . '/footer.php'; ?>