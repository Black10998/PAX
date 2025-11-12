/**
 * Live Agent Frontend JavaScript
 * User-side chat interface
 */

/* global jQuery, window, document */
(function($) {
    'use strict';

    class LiveAgentFrontend {
        constructor() {
            this.sessionId = null;
            this.state = {
                phase: 'idle', // idle|connecting|queued|connected|closed
                pollDelay: 1000,
                pollTimer: null,
                pollInFlight: false,
                lastMessageId: 0,
                statusEtag: null,
                messagesEtag: null,
                typingTimeout: null,
                viewportBound: false
            };

            this.refs = {};
            this.typingDebounce = null;
        }

        init() {
            if ( ! window.paxLiveAgent ) {
                return;
            }

            this.mountLauncher();
            this.bindGlobalEvents();
            this.checkExistingSession();
        }

        mountLauncher() {
            const position = window.paxLiveAgent.buttonPosition || 'bottom-right';
            const text = window.paxLiveAgent.buttonText || 'Live Support';

            if ( document.getElementById( 'pax-liveagent-launcher' ) ) {
                return;
            }

            const launcher = document.createElement( 'button' );
            launcher.id = 'pax-liveagent-launcher';
            launcher.type = 'button';
            launcher.className = `pax-liveagent-launcher pax-launcher-${position}`;
            launcher.setAttribute( 'aria-haspopup', 'dialog' );
            launcher.setAttribute( 'aria-expanded', 'false' );
            launcher.innerHTML = `
                <span class="pax-launcher-icon" aria-hidden="true">
                    <span class="dashicons dashicons-format-chat"></span>
                </span>
                <span class="pax-launcher-label">${this.escapeHtml( text )}</span>
            `;

            document.body.appendChild( launcher );

            this.refs.launcher = launcher;
        }

        bindGlobalEvents() {
            if ( this.refs.launcher ) {
                this.refs.launcher.addEventListener( 'click', () => this.startChat() );
            }

            document.addEventListener( 'click', ( event ) => {
                if (
                    event.target.closest &&
                    event.target.closest( '#pax-liveagent-close' )
                ) {
                    this.closeChatWindow();
                }
            } );
        }

        async checkExistingSession() {
            if ( ! window.paxLiveAgent.isLoggedIn ) {
                return;
            }

            try {
                const url = new URL( `${window.paxLiveAgent.restUrl}/liveagent/sessions/list` );
                url.searchParams.append( 'status', 'active' );
                url.searchParams.append( 'limit', '1' );

                const response = await this.requestJSON( url.toString(), {
                    method: 'GET'
                } );

                if ( response?.success && Array.isArray( response.sessions ) && response.sessions.length > 0 ) {
                    const session = response.sessions[ 0 ];
                    if ( parseInt( session.user_id, 10 ) === parseInt( window.paxLiveAgent.userId, 10 ) ) {
                        this.sessionId = session.id;
                        this.showChatWindow();
                        await this.loadMessages();
                        this.setPhase( 'connected' );
                        this.schedulePoll( 100 );
                    }
                }
            } catch ( error ) {
                console.error( 'LiveAgent: failed to resume session', error );
            }
        }

        async startChat() {
            if ( ! window.paxLiveAgent.isLoggedIn ) {
                this.showLoginPrompt();
                return;
            }

            if ( this.sessionId ) {
                this.showChatWindow();
                return;
            }

            this.setPhase( 'connecting' );
            this.showChatWindow();

            try {
                const response = await this.requestJSON(
                    `${window.paxLiveAgent.restUrl}/liveagent/session/create`,
                    {
                        method: 'POST',
                        body: {
                            user_id: window.paxLiveAgent.userId,
                            page_url: window.location.href
                        }
                    }
                );

                if ( response?.success ) {
                    this.sessionId = response.session_id;
                    this.setPhase( 'queued' );
                    await this.loadMessages();
                    this.schedulePoll( 100 );
                } else {
                    this.showToast( window.paxLiveAgent.strings.errorStarting || 'Unable to start a chat right now.' );
                    this.setPhase( 'idle' );
                    this.closeChatWindow();
                }
            } catch ( error ) {
                console.error( 'LiveAgent: start chat failed', error );
                this.showToast( window.paxLiveAgent.strings.errorStarting || 'Unable to start a chat right now.' );
                this.setPhase( 'idle' );
                this.closeChatWindow();
            }
        }

        setPhase( phase ) {
            this.state.phase = phase;

            if ( this.refs.statusText ) {
                let text = '';
                switch ( phase ) {
                    case 'connecting':
                        text = window.paxLiveAgent.strings.connecting || 'Connecting…';
                        break;
                    case 'queued':
                        text = window.paxLiveAgent.strings.queued || 'Queued — stay close!';
                        break;
                    case 'connected':
                        text = window.paxLiveAgent.strings.agentConnected || 'Agent connected!';
                        break;
                    case 'closed':
                        text = window.paxLiveAgent.strings.chatEnded || 'Chat ended';
                        break;
                    default:
                        text = window.paxLiveAgent.buttonText || 'Live Support';
                }
                this.refs.statusText.textContent = text;
            }

            if ( this.refs.overlay ) {
                this.refs.overlay.setAttribute( 'data-phase', phase );
            }
        }

        async loadMessages() {
            if ( ! this.sessionId ) {
                return;
            }

            try {
                const url = `${window.paxLiveAgent.restUrl}/liveagent/messages/${this.sessionId}`;
                const response = await this.requestJSON( url, {
                    method: 'GET',
                    storeEtagKey: 'messages'
                } );

                if ( ! response?.success ) {
                    this.renderWelcome();
                    return;
                }

                const list = Array.isArray( response.messages ) ? response.messages : [];
                this.clearMessages();

                if ( list.length === 0 ) {
                    this.renderWelcome();
                } else {
                    this.appendMessage( list[ 0 ], false );
                    for ( let i = 1; i < list.length; i += 1 ) {
                        this.appendMessage( list[ i ], true );
                    }
                    this.state.lastMessageId = response.last_id ? parseInt( response.last_id, 10 ) : this.state.lastMessageId;
                    this.scrollToBottom( true );
                }

                await this.markMessagesRead();
            } catch ( error ) {
                console.error( 'LiveAgent: load messages failed', error );
            }
        }

        showChatWindow() {
            if ( this.refs.launcher ) {
                this.refs.launcher.setAttribute( 'aria-expanded', 'true' );
                this.refs.launcher.classList.add( 'pax-launcher-hidden' );
            }

            if ( this.refs.overlay ) {
                this.refs.overlay.removeAttribute( 'hidden' );
                this.refs.input?.focus();
                return;
            }

            const overlay = document.createElement( 'div' );
            overlay.id = 'pax-liveagent-overlay';
            overlay.className = 'pax-liveagent-overlay';
            overlay.setAttribute( 'role', 'dialog' );
            overlay.setAttribute( 'aria-modal', 'true' );
            overlay.setAttribute( 'data-phase', this.state.phase );

            overlay.innerHTML = `
                <div class="pax-liveagent-surface" style="height: var(--pax-liveagent-viewport, 100svh);">
                    <header class="pax-liveagent-topbar">
                        <div class="pax-liveagent-status" aria-live="polite">
                            <span class="pax-status-dot" aria-hidden="true"></span>
                            <span class="pax-status-text" id="pax-liveagent-status-text">${window.paxLiveAgent.strings.connecting || 'Connecting…'}</span>
                        </div>
                        <button type="button" class="pax-liveagent-close" id="pax-liveagent-close" aria-label="${this.escapeHtml( window.paxLiveAgent.strings.close || 'Close chat' )}">
                            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                        </button>
                    </header>
                    <main class="pax-liveagent-body">
                        <section class="pax-liveagent-timeline" id="pax-liveagent-messages" role="log" aria-live="polite" aria-relevant="additions text"></section>
                        <div class="pax-liveagent-typing" id="pax-liveagent-typing" hidden>
                            <span class="pax-typing-dot"></span>
                            <span class="pax-typing-dot"></span>
                            <span class="pax-typing-dot"></span>
                            <span class="pax-typing-text">${this.escapeHtml( window.paxLiveAgent.strings.agentTyping || 'Agent is typing…' )}</span>
                        </div>
                    </main>
                    <footer class="pax-liveagent-composer" id="pax-liveagent-composer">
                        <label class="screen-reader-text" for="pax-liveagent-input">${this.escapeHtml( window.paxLiveAgent.strings.typeMessage || 'Type a message' )}</label>
                        <textarea id="pax-liveagent-input" rows="1" placeholder="${this.escapeHtml( window.paxLiveAgent.strings.typeMessage || 'Type a message…' )}" autocomplete="off" aria-label="${this.escapeHtml( window.paxLiveAgent.strings.typeMessage || 'Type a message' )}"></textarea>
                        <button type="button" class="pax-liveagent-send" id="pax-liveagent-send" aria-label="${this.escapeHtml( window.paxLiveAgent.strings.send || 'Send message' )}">
                            <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                            <span class="pax-send-label">${this.escapeHtml( window.paxLiveAgent.strings.send || 'Send' )}</span>
                        </button>
                        <span class="pax-composer-hint" id="pax-liveagent-hint">${this.escapeHtml( window.paxLiveAgent.strings.enterHint || 'Press Enter to send · Shift+Enter for newline' )}</span>
                    </footer>
                </div>
            `;

            document.body.appendChild( overlay );

            this.refs.overlay = overlay;
            this.refs.messages = overlay.querySelector( '#pax-liveagent-messages' );
            this.refs.statusText = overlay.querySelector( '#pax-liveagent-status-text' );
            this.refs.typing = overlay.querySelector( '#pax-liveagent-typing' );
            this.refs.input = overlay.querySelector( '#pax-liveagent-input' );
            this.refs.sendButton = overlay.querySelector( '#pax-liveagent-send' );
            this.refs.composer = overlay.querySelector( '#pax-liveagent-composer' );

            overlay.addEventListener( 'keydown', ( event ) => {
                if ( event.key === 'Escape' ) {
                    this.closeChatWindow();
                }
            } );

            this.bindChatEvents();
            this.updateViewportBinding();
            this.setPhase( this.state.phase || 'connecting' );
            this.refs.input?.focus();
        }

        bindChatEvents() {
            if ( ! this.refs.input ) {
                return;
            }

            this.refs.input.addEventListener( 'keydown', ( event ) => {
                if ( event.key === 'Enter' && ! event.shiftKey ) {
                    event.preventDefault();
                    this.sendMessage();
                }
            } );

            this.refs.input.addEventListener( 'input', () => {
                this.autoGrowInput();
                this.emitTyping();
            } );

            if ( this.refs.sendButton ) {
                this.refs.sendButton.addEventListener( 'click', () => this.sendMessage() );
            }
        }

        autoGrowInput() {
            if ( ! this.refs.input ) {
                return;
            }
            this.refs.input.style.height = 'auto';
            const maxHeight = 200;
            this.refs.input.style.height = `${Math.min( this.refs.input.scrollHeight, maxHeight )}px`;
        }

        updateViewportBinding() {
            if ( this.state.viewportBound ) {
                return;
            }

            const apply = () => {
                const height = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                document.documentElement.style.setProperty( '--pax-liveagent-viewport', `${height}px` );
            };

            apply();

            if ( window.visualViewport ) {
                window.visualViewport.addEventListener( 'resize', apply );
                window.visualViewport.addEventListener( 'scroll', apply );
            } else {
                window.addEventListener( 'resize', apply );
            }

            this.state.viewportBound = true;
        }

        async pollUpdates( immediate = false ) {
            if ( ! this.sessionId || this.state.pollInFlight ) {
                return;
            }

            this.state.pollInFlight = true;

            try {
                const url = new URL( `${window.paxLiveAgent.restUrl}/liveagent/status/poll` );
                url.searchParams.append( 'session_id', this.sessionId );
                if ( this.state.lastMessageId > 0 ) {
                    url.searchParams.append( 'after', String( this.state.lastMessageId ) );
                }

                const result = await this.requestJSON( url.toString(), {
                    method: 'GET',
                    ifNoneMatchKey: this.state.lastMessageId > 0 ? 'status' : null,
                    storeEtagKey: 'status'
                } );

                if ( result === 304 || result === null ) {
                    return;
                }

                if ( result?.success ) {
                    const incoming = Array.isArray( result.new_messages ) ? result.new_messages : [];

                    if ( incoming.length > 0 ) {
                        incoming.forEach( ( message ) => this.appendMessage( message, true ) );
                        this.state.lastMessageId = result.last_id ? parseInt( result.last_id, 10 ) : this.state.lastMessageId;
                        await this.markMessagesRead();
                    } else if ( typeof result.last_id === 'number' ) {
                        this.state.lastMessageId = Math.max( this.state.lastMessageId, parseInt( result.last_id, 10 ) );
                    }

                    this.toggleTypingIndicator( result.agent_typing );
                    this.syncSessionStatus( result.session_status );
                }
            } catch ( error ) {
                console.error( 'LiveAgent: poll failed', error );
            } finally {
                this.state.pollInFlight = false;
                this.schedulePoll( immediate ? 250 : this.state.pollDelay );
            }
        }

        schedulePoll( delay = this.state.pollDelay ) {
            if ( this.state.pollTimer ) {
                window.clearTimeout( this.state.pollTimer );
            }
            if ( this.state.phase === 'closed' ) {
                return;
            }
            this.state.pollTimer = window.setTimeout( () => this.pollUpdates(), Math.max( 250, delay ) );
        }

        syncSessionStatus( status ) {
            if ( ! status ) {
                return;
            }

            const normalized = status === 'active' ? 'connected' : status;

            if ( normalized === 'closed' || normalized === 'declined' ) {
                this.setPhase( 'closed' );
                this.showChatEnded();
                return;
            }

            if ( normalized === 'connected' && this.state.phase !== 'connected' ) {
                this.setPhase( 'connected' );
                this.showAgentJoined();
            }
        }

        toggleTypingIndicator( isTyping ) {
            if ( ! this.refs.typing ) {
                return;
            }
            if ( isTyping ) {
                this.refs.typing.removeAttribute( 'hidden' );
            } else {
                this.refs.typing.setAttribute( 'hidden', 'hidden' );
            }
        }

        async sendMessage() {
            if ( ! this.sessionId || ! this.refs.input ) {
                return;
            }

            const raw = this.refs.input.value.trim();
            if ( ! raw || this.state.phase === 'closed' ) {
                return;
            }

            if ( this.refs.sendButton ) {
                this.refs.sendButton.disabled = true;
                this.refs.sendButton.classList.add( 'is-sending' );
            }

            try {
                const response = await this.requestJSON(
                    `${window.paxLiveAgent.restUrl}/liveagent/message/send`,
                    {
                        method: 'POST',
                        body: {
                            session_id: this.sessionId,
                            message: raw,
                            sender: 'user'
                        }
                    }
                );

                if ( response?.success && response.message ) {
                    this.refs.input.value = '';
                    this.autoGrowInput();
                    this.appendMessage( response.message, true );
                    this.state.lastMessageId = response.message.id ? parseInt( response.message.id, 10 ) : this.state.lastMessageId;
                    await this.pollUpdates( true );
                } else {
                    this.showToast( window.paxLiveAgent.strings.messageFailed || 'Message failed to send.' );
                }
            } catch ( error ) {
                console.error( 'LiveAgent: send failed', error );
                this.showToast( window.paxLiveAgent.strings.messageFailed || 'Message failed to send.' );
            } finally {
                if ( this.refs.sendButton ) {
                    window.setTimeout( () => {
                        this.refs.sendButton.disabled = false;
                        this.refs.sendButton.classList.remove( 'is-sending' );
                    }, 250 );
                }
                this.emitTyping( false );
            }
        }

        async markMessagesRead() {
            if ( ! this.sessionId ) {
                return;
            }

            try {
                await this.requestJSON(
                    `${window.paxLiveAgent.restUrl}/liveagent/message/mark-read`,
                    {
                        method: 'POST',
                        body: {
                            session_id: this.sessionId,
                            reader_type: 'user'
                        }
                    }
                );
            } catch ( error ) {
                // Silently fail
            }
        }

        emitTyping( status = true ) {
            if ( ! this.sessionId ) {
                return;
            }

            if ( status ) {
                if ( this.typingDebounce ) {
                    window.clearTimeout( this.typingDebounce );
                }
                this.typingDebounce = window.setTimeout( () => this.emitTyping( false ), 2000 );
            }

            this.requestJSON(
                `${window.paxLiveAgent.restUrl}/liveagent/status/typing`,
                {
                    method: 'POST',
                    body: {
                        session_id: this.sessionId,
                        is_typing: !! status,
                        sender: 'user'
                    }
                }
            ).catch( () => {} );
        }

        appendMessage( message, append = true ) {
            if ( ! this.refs.messages ) {
                return;
            }

            const fragment = document.createDocumentFragment();
            const wrapper = document.createElement( 'article' );
            const sender = message.sender === 'agent' ? 'agent' : message.sender === 'system' ? 'system' : 'user';
            wrapper.className = `pax-chat-bubble pax-from-${sender}`;

            const text = document.createElement( 'div' );
            text.className = 'pax-chat-text';
            text.innerHTML = this.escapeHtml( message.message || '' );
            wrapper.appendChild( text );

            if ( message.attachment && message.attachment.url ) {
                const attachment = document.createElement( 'a' );
                attachment.className = 'pax-chat-attachment';
                attachment.href = message.attachment.url;
                attachment.target = '_blank';
                attachment.rel = 'noopener noreferrer';
                attachment.textContent = message.attachment.filename || window.paxLiveAgent.strings.download || 'Download';
                wrapper.appendChild( attachment );
            }

            const meta = document.createElement( 'div' );
            meta.className = 'pax-chat-meta';
            meta.textContent = this.formatTimestamp( message.timestamp );
            wrapper.appendChild( meta );

            fragment.appendChild( wrapper );

            if ( append ) {
                this.refs.messages.appendChild( fragment );
            } else {
                this.refs.messages.innerHTML = '';
                this.refs.messages.appendChild( fragment );
            }

            this.scrollToBottom();
        }

        renderWelcome() {
            if ( ! this.refs.messages ) {
                return;
            }
            const welcome = document.createElement( 'div' );
            welcome.className = 'pax-chat-system';
            welcome.innerHTML = this.escapeHtml( window.paxLiveAgent.welcomeMessage || 'Thanks for reaching out! An agent will join shortly.' );
            this.refs.messages.appendChild( welcome );
        }

        clearMessages() {
            if ( this.refs.messages ) {
                this.refs.messages.innerHTML = '';
            }
        }

        scrollToBottom( force = false ) {
            if ( ! this.refs.messages ) {
                return;
            }

            const node = this.refs.messages;
            const atBottom = Math.abs( node.scrollHeight - node.scrollTop - node.clientHeight ) < 24;

            if ( force || atBottom ) {
                node.scrollTo( {
                    top: node.scrollHeight,
                    behavior: 'smooth'
                } );
            }
        }

        showAgentJoined() {
            if ( ! this.refs.messages ) {
                return;
            }
            const notice = document.createElement( 'div' );
            notice.className = 'pax-chat-system pax-chat-system--success';
            notice.textContent = window.paxLiveAgent.strings.agentConnected || 'An agent is now connected.';
            this.refs.messages.appendChild( notice );
            this.scrollToBottom( true );
        }

        showChatEnded() {
            if ( this.refs.composer ) {
                this.refs.composer.setAttribute( 'hidden', 'hidden' );
            }

            if ( ! this.refs.messages ) {
                return;
            }
            const notice = document.createElement( 'div' );
            notice.className = 'pax-chat-system pax-chat-system--ended';
            notice.textContent = window.paxLiveAgent.strings.chatEnded || 'This chat has ended.';
            this.refs.messages.appendChild( notice );
            this.scrollToBottom( true );
        }

        closeChatWindow() {
            if ( this.state.pollTimer ) {
                window.clearTimeout( this.state.pollTimer );
                this.state.pollTimer = null;
            }

            if ( this.refs.overlay ) {
                this.refs.overlay.setAttribute( 'hidden', 'hidden' );
            }

            if ( this.refs.launcher ) {
                this.refs.launcher.setAttribute( 'aria-expanded', 'false' );
                this.refs.launcher.classList.remove( 'pax-launcher-hidden' );
            }
        }

        showLoginPrompt() {
            if ( document.getElementById( 'pax-login-prompt' ) ) {
                return;
            }

            const wrapper = document.createElement( 'div' );
            wrapper.className = 'pax-liveagent-modal';
            wrapper.id = 'pax-login-prompt';
            wrapper.innerHTML = `
                <div class="pax-modal-content">
                    <div class="pax-modal-header">
                        <h3>${this.escapeHtml( window.paxLiveAgent.strings.loginRequired || 'Login required' )}</h3>
                        <button type="button" class="pax-modal-close" aria-label="${this.escapeHtml( window.paxLiveAgent.strings.close || 'Close' )}">&times;</button>
                    </div>
                    <div class="pax-modal-body">
                        <p>${this.escapeHtml( window.paxLiveAgent.strings.loginPrompt || 'You need to be logged in to start a live chat with our team.' )}</p>
                        <a class="pax-btn-primary" href="${this.escapeHtml( window.paxLiveAgent.loginUrl )}">
                            ${this.escapeHtml( window.paxLiveAgent.strings.login || 'Log in' )}
                        </a>
                    </div>
                </div>
            `;

            wrapper.addEventListener( 'click', ( event ) => {
                if ( event.target === wrapper || event.target.classList.contains( 'pax-modal-close' ) ) {
                    wrapper.remove();
                }
            } );

            document.body.appendChild( wrapper );
        }

        showToast( message ) {
            if ( ! message ) {
                return;
            }

            const toast = document.createElement( 'div' );
            toast.className = 'pax-liveagent-toast';
            toast.textContent = message;
            document.body.appendChild( toast );

            window.setTimeout( () => {
                toast.classList.add( 'is-exiting' );
                window.setTimeout( () => toast.remove(), 250 );
            }, 3000 );
        }

        formatTimestamp( timestamp ) {
            if ( ! timestamp ) {
                return '';
            }
            const date = new Date( timestamp );
            if ( Number.isNaN( date.getTime() ) ) {
                return '';
            }
            return date.toLocaleTimeString( [], { hour: 'numeric', minute: '2-digit' } );
        }

        escapeHtml( value ) {
            const div = document.createElement( 'div' );
            div.textContent = value == null ? '' : String( value );
            return div.innerHTML;
        }

        async requestJSON( url, options = {} ) {
            const headers = new Headers();
            headers.set( 'Accept', 'application/json' );

            if ( options.body && ! ( options.body instanceof FormData ) ) {
                headers.set( 'Content-Type', 'application/json' );
            }

            if ( window.paxLiveAgent?.nonce ) {
                headers.set( 'X-WP-Nonce', window.paxLiveAgent.nonce );
            }

            if ( options.ifNoneMatchKey && this.state[ `${options.ifNoneMatchKey}Etag` ] ) {
                headers.set( 'If-None-Match', this.state[ `${options.ifNoneMatchKey}Etag` ] );
            }

            const fetchOptions = {
                method: options.method || 'GET',
                headers,
                credentials: 'same-origin',
                cache: 'no-store'
            };

            if ( options.body ) {
                fetchOptions.body = options.body instanceof FormData ? options.body : JSON.stringify( options.body );
            }

            const response = await fetch( url, fetchOptions );

            if ( response.status === 304 ) {
                return 304;
            }

            const etag = response.headers.get( 'ETag' );
            if ( options.storeEtagKey && etag ) {
                this.state[ `${options.storeEtagKey}Etag` ] = etag;
            }

            if ( ! response.ok ) {
                throw new Error( `HTTP ${response.status}` );
            }

            return response.json();
        }
    }

    $( () => {
        if ( typeof window.paxLiveAgent !== 'undefined' ) {
            window.liveAgentFrontend = new LiveAgentFrontend();
            window.liveAgentFrontend.init();
        }
    } );
})( jQuery );
