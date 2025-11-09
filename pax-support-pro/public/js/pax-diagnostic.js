/**
 * PAX Support Pro - Diagnostic Script
 * Run this in browser console to diagnose chat visibility issues
 * 
 * Usage: Copy and paste this entire script into browser console
 */

(function() {
    console.group('🔍 PAX Chat Diagnostic Report');
    console.log('Timestamp:', new Date().toISOString());
    
    // 1. Check DOM elements
    console.group('1️⃣ DOM Elements');
    const launcher = document.getElementById('pax-unified-launcher');
    const chatWindow = document.getElementById('pax-chat');
    const overlay = document.getElementById('pax-chat-overlay');
    const messages = document.getElementById('pax-messages');
    const input = document.getElementById('pax-input');
    
    console.log('Launcher:', launcher ? '✅ Found' : '❌ Missing');
    console.log('Chat Window:', chatWindow ? '✅ Found' : '❌ Missing');
    console.log('Overlay:', overlay ? '✅ Found' : '❌ Missing');
    console.log('Messages Container:', messages ? '✅ Found' : '❌ Missing');
    console.log('Input Field:', input ? '✅ Found' : '❌ Missing');
    console.groupEnd();
    
    if (!chatWindow) {
        console.error('❌ Chat window element not found! Cannot continue diagnostic.');
        console.groupEnd();
        return;
    }
    
    // 2. Check classList
    console.group('2️⃣ Chat Window Classes');
    console.log('classList:', Array.from(chatWindow.classList));
    console.log('Has .open class:', chatWindow.classList.contains('open') ? '✅ Yes' : '❌ No');
    console.log('Has .modal-mode class:', chatWindow.classList.contains('modal-mode') ? '✅ Yes' : '❌ No');
    console.groupEnd();
    
    // 3. Check computed styles
    console.group('3️⃣ Computed CSS Styles');
    const styles = window.getComputedStyle(chatWindow);
    const styleReport = {
        display: styles.display,
        opacity: styles.opacity,
        visibility: styles.visibility,
        transform: styles.transform,
        pointerEvents: styles.pointerEvents,
        zIndex: styles.zIndex,
        position: styles.position,
        width: styles.width,
        height: styles.height,
        top: styles.top,
        left: styles.left,
        right: styles.right,
        bottom: styles.bottom
    };
    console.table(styleReport);
    
    // Check if visible
    const isVisible = styles.display !== 'none' && 
                     styles.visibility !== 'hidden' && 
                     parseFloat(styles.opacity) > 0;
    console.log('Is Visible:', isVisible ? '✅ Yes' : '❌ No');
    console.groupEnd();
    
    // 4. Check CSS rules
    console.group('4️⃣ CSS Rules Analysis');
    const sheets = Array.from(document.styleSheets);
    let paxChatRules = [];
    
    sheets.forEach(sheet => {
        try {
            const rules = Array.from(sheet.cssRules || sheet.rules || []);
            rules.forEach(rule => {
                if (rule.selectorText && rule.selectorText.includes('#pax-chat')) {
                    paxChatRules.push({
                        selector: rule.selectorText,
                        display: rule.style.display || 'not set',
                        opacity: rule.style.opacity || 'not set',
                        visibility: rule.style.visibility || 'not set',
                        sheet: sheet.href || 'inline'
                    });
                }
            });
        } catch (e) {
            // Cross-origin stylesheets will throw errors
        }
    });
    
    console.log('Found', paxChatRules.length, 'CSS rules for #pax-chat');
    console.table(paxChatRules);
    console.groupEnd();
    
    // 5. Check JavaScript state
    console.group('5️⃣ JavaScript State');
    console.log('window.paxSupportPro:', window.paxSupportPro ? '✅ Loaded' : '❌ Missing');
    console.log('window.paxUnifiedChat:', window.paxUnifiedChat ? '✅ Loaded' : '❌ Missing');
    console.log('window.PAX_DEBUG_MODE:', window.PAX_DEBUG_MODE);
    
    if (window.paxUnifiedChat) {
        console.log('chatWindow reference:', window.paxUnifiedChat.chatWindow ? '✅ Set' : '❌ Null');
        console.log('currentMode:', window.paxUnifiedChat.currentMode);
    }
    console.groupEnd();
    
    // 6. Test manual toggle
    console.group('6️⃣ Manual Toggle Test');
    console.log('Attempting to add .open class manually...');
    chatWindow.classList.add('open');
    
    setTimeout(() => {
        const newStyles = window.getComputedStyle(chatWindow);
        console.log('After adding .open class:');
        console.table({
            display: newStyles.display,
            opacity: newStyles.opacity,
            visibility: newStyles.visibility,
            transform: newStyles.transform
        });
        
        const nowVisible = newStyles.display !== 'none' && 
                          newStyles.visibility !== 'hidden' && 
                          parseFloat(newStyles.opacity) > 0;
        console.log('Is Now Visible:', nowVisible ? '✅ Yes' : '❌ No');
        
        if (!nowVisible) {
            console.error('❌ PROBLEM: Adding .open class did not make chat visible!');
            console.error('This indicates a CSS specificity or !important issue.');
        } else {
            console.log('✅ SUCCESS: Chat is now visible with .open class');
        }
        
        // Remove the class
        chatWindow.classList.remove('open');
        console.log('Removed .open class for cleanup');
        console.groupEnd();
        
        // 7. Recommendations
        console.group('7️⃣ Recommendations');
        if (!nowVisible) {
            console.log('🔧 Suggested fixes:');
            console.log('1. Check if #pax-chat.open has display:flex !important');
            console.log('2. Check if media queries override the .open styles');
            console.log('3. Check browser DevTools > Elements > Computed to see which rule wins');
            console.log('4. Try adding inline style: chatWindow.style.display = "flex"');
        } else {
            console.log('✅ CSS is working correctly');
            console.log('🔧 Check JavaScript event bindings:');
            console.log('1. Verify launcher click handler is bound');
            console.log('2. Check if openChat() is being called');
            console.log('3. Enable PAX_DEBUG_MODE for detailed logs');
        }
        console.groupEnd();
        
        console.groupEnd(); // End main group
    }, 100);
})();
