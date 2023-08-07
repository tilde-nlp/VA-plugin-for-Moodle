/**
 * @package   block_tildeva
 * @copyright 2023, Evita Korņējeva <evita.kornejeva@tilde.lv>
 * @copyright 2023, SIA Tilde
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * @namespace M.block_tildeva_ajax
 */
M.block_tildeva_ajax = M.block_tildeva_ajax || {};

/**
 * Init ajax based Chat UI.
 * @namespace M.block_tildeva_ajax
 * @function
 * @param {YUI} Y
 * @param {Object} cfg configuration data
 */
M.block_tildeva_ajax.init = function (Y, cfg) {

    var gui_ajax = {

        // Properties.
        api: M.cfg.wwwroot + '/blocks/tildeva/chat_ajax.php?sesskey=' + M.cfg.sesskey,  // The path to the ajax callback script.
        cfg: {},

        // Elements
        wchat_maximize: null,
        wchat_close: null,
        wchat_minimize: null,
        wchat_container: null,
        directLine: null,
        store: null,
        chatParams: {},
        cID: null,
        respondToMsg: false,
        msgId: null,
        init: function (cfg) {
            this.chatParams['locale'] = 'lv-lv';
            this.chatParams['host'] = location.protocol + '//' + location.hostname;
            this.chatParams['href'] = location.href;
            this.cfg = cfg;
            this.cfg.req_count = this.cfg.req_count || 0;

            participantswidth = 180;
            this.wchat_maximize = Y.one('#wchat__maximize-wrapper');
            this.wchat_container = Y.one('#wchat__container');
            this.wchat_minimize = Y.one('#wchat__minimize');
            this.wchat_close = Y.one('#wchat__close');
            this.wchat_maximize.on('click', this.wchat__maximize, this);
            this.wchat_minimize.on('click', this.wchat__minimize, this);
            this.wchat_close.on('click', this.wchat__close, this);



            // // Prepare and execute the first AJAX request of information.
            Y.io(this.api, {
                method: 'POST',
                data: build_querystring({
                    action: 'init',
                    chat_usid: this.cfg.userid,
                    chat_couid: this.cfg.courseid
                }),
                on: {
                    success: function (tid, outcome) {
                        try {
                            var data = Y.JSON.parse(outcome.responseText);
                            for (let key in data) {
                                if (data.hasOwnProperty(key)) {
                                    this.chatParams[key] = data[key];
                                }
                            }
                            ;
                        } catch (ex) {
                            return;
                        }
                    }
                },
                context: this
            });


        },

        wchat__maximize: function () {
            this.wchat_maximize.setAttribute('hidden');
            this.wchat_container.removeAttribute('hidden');
            this.cID = this.chatParams['conversationid'];

            Y.io(`${this.cfg.boturl}/conversations/${this.cID}`, {
                method: 'GET',
                data: build_querystring({}),
                on: {
                    success: function (tid, outcome) {
                        try {
                            this.joinWebchat();
                        } catch (ex) {
                            console.log("Error joining webchat", error)
                            return;
                        }
                    },
                    failure: function (id, response) {
                        this.cID = null;
                        this.joinWebchat();
                    }
                },
                context: this
            });
        },

        wchat__minimize: function () {
            this.wchat_maximize.removeAttribute('hidden');
            this.wchat_container.setAttribute('hidden');
        },
        wchat__close: function () {
            this.directLine.postActivity({
                from: { id: this.cfg.userid }, // required (from.name is optional)
                type: 'endOfConversation'
            }).subscribe(
                id => {
                    this.directLine.end();
                    this.directLine = null;
                    this.store = null;
                    this.chatParams.conversationid = null;
                },
                error => console.log("Error posting activity", error)
            );


            this.wchat_maximize.removeAttribute('hidden');
            this.wchat_container.setAttribute('hidden');
            // // Prepare and execute the first AJAX request of information.
            Y.io(this.api, {
                method: 'POST',
                data: build_querystring({
                    action: 'deleteconversation',
                    chat_usid: this.cfg.userid,
                    chat_couid: this.cfg.courseid
                }),
                on: {
                    success: function (tid, outcome) {
                        try {
                            console.log('Conversation deleted:' + outcome.responseText);

                        } catch (ex) {
                            return;
                        }
                    }
                },
                context: this
            });

        },

        joinWebchat: function () {
            if (this.store == null) {
                this.store = window.WebChat.createStore(
                    {}, ({ dispatch }) => next => action => {
                        if (action.type === 'DIRECT_LINE/CONNECT_FULFILLED') {


                        } else if (action.type === 'DIRECT_LINE/INCOMING_ACTIVITY') {
                            if (action?.payload?.activity?.text?.startsWith("command:")) {
                                return next;
                            }
                        }
                        else if (action.type === 'WEB_CHAT/SEND_MESSAGE') {

                        }

                        else if (action.type === "WEB_CHAT/SET_SUGGESTED_ACTIONS") {

                        }
                        if (action.type === 'DIRECT_LINE/INCOMING_ACTIVITY') {

                        }
                        if (action.type == 'endOfConversation') {
                            return next;
                        }

                        return next(action);
                    }
                )
            }
            if (this.directLine == null) {
                this.directLine = window.WebChat.createDirectLine({
                    secret: null,
                    token: null,
                    domain: this.cfg.boturl,
                    webSocket: false,
                    conversationId: this.cID
                });
                this.directLine.activity$
                    .filter(activity => activity.type === 'event')
                    .subscribe(message => {
                        if (message && message.id && message.id === this.msgId) {
                            this.respondToMsg = true;
                        }
                    });
                this.directLine.activity$
                    .filter(activity => activity.type === 'message' && activity.from.name === "Bot")
                    .subscribe(message => {
                        if (this.respondToMsg && message && message.text && message.text.startsWith("command:set_send_box")) {
                            this.store.dispatch({
                                type: 'WEB_CHAT/SET_SEND_BOX',
                                payload: {
                                  text: message.text.substring(21)
                                }
                              });
                        }
                        else if (this.respondToMsg && message && message.text && message.text.startsWith("command:")) {
                            Y.io(this.api, {
                                method: 'POST',
                                data: build_querystring({
                                    action: 'msg',
                                    chat_msg: message.text,
                                    chat_usid: this.cfg.userid,
                                    chat_couid: this.cfg.courseid
                                }),
                                on: {
                                    success: this.send_callback
                                },
                                context: this
                            });
                            message = null;
                        }
                    });

                this.directLine.connectionStatus$
                    .subscribe(connectionStatus => {
                        switch (connectionStatus) {
                            //case 0: //ConnectionStatus.Uninitialized:    // the status when the DirectLine object is first created/constructed
                            //case 1: //ConnectionStatus.Connecting:       // currently trying to connect to the conversation
                            case 2: //ConnectionStatus.Online:           // successfully connected to the converstaion. Connection is healthy so far as we know.
                                Y.io(this.api, {
                                    method: 'POST',
                                    data: build_querystring({
                                        action: 'sid',
                                        chat_usid: this.cfg.userid,
                                        chat_couid: this.cfg.courseid,
                                        chat_convid: encodeURIComponent(this.directLine.conversationId)
                                    }),
                                    on: {
                                        success: function (tid, outcome) { },
                                        failure: function (id, response) {
                                            console.error('Error:', response.statusText);
                                            // Handle the failure/error here
                                        }
                                    },
                                    context: this
                                });

                                var value = JSON.stringify(this.chatParams);
                                !this.cID?.length
                                    ? this.directLine.postActivity({
                                        from: { id: this.cfg.userid }, // required (from.name is optional)
                                        type: 'event',
                                        name: 'webchat/join', value: value
                                    }).subscribe(
                                        id => { /*console.log("Posted activity, assigned ID ", id);*/ this.msgId = id },
                                        error => console.log("Error posting activity", error)
                                    )
                                    : this.directLine.postActivity({
                                        from: { id: this.cfg.userid }, // required (from.name is optional)
                                        type: 'event',
                                        name: 'webchat/context', value: value
                                    }).subscribe(
                                        id => { /*console.log("Posted activity, assigned ID ", id);*/ this.msgId = id },
                                        error => console.log("Error posting activity", error)
                                    );
                                break;
                            //case 3: //ConnectionStatus.ExpiredToken:     // last operation errored out with an expired token. Your app should supply a new one.
                            //case 4: //ConnectionStatus.FailedToConnect:  // the initial attempt to connect to the conversation failed. No recovery possible.
                            // case 5: //ConnectionStatus.Ended:            // the bot ended the conversation

                        }
                    });
            }
            window.WebChat.renderWebChat({
                styleOptions: this.styleOptions(),
                directLine: this.directLine,
                store: this.store
            }, document.getElementById('webchat'));
        },

        styleOptions: function () {
            return {
                fontSizeSmall: '70%',
                botAvatarImage: 'https://va.tilde.com/api/prodk8sbotcava0/media/staging/avatar.jpg',
                botAvatarBackgroundColor: 'transparent',
                botAvatarInitials: 'VA',
                hideUploadButton: true,
                backgroundColor: '#fff',
                sendBoxBackground: '#C9DC50',
                sendBoxBorderTop: '1px solid #CCC',
                sendBoxPlaceholderColor: '#605e5c',
                sendBoxTextColor: '#606060',
                sendBoxButtonColorOnActive: '#C9DC50',
                sendBoxButtonColorOnFocus: '#C9DC50',
                sendBoxButtonColorOnHover: '#C9DC50',
                sendBoxButtonShadeColor: 'transparent',
                sendBoxButtonShadeColorOnActive: 'transparent',
                sendBoxButtonShadeColorOnDisabled: 'transparent',
                sendBoxButtonShadeColorOnFocus: 'transparent',
                sendBoxButtonShadeColorOnHover: 'transparent',
                transcriptActivityVisualKeyboardIndicatorColor: 'transparent',
                bubbleBackground: '#eef2f8',
                bubbleTextColor: '#606060',
                markdownRespectCRLF: true,
                bubbleBorderWidth: 0,
                bubbleFromUserBorderWidth: 0,
                bubbleFromUserBackground: '#C9DC50',
                bubbleFromUserTextColor: '#ffffff',
                paddingRegular: '15px',
                subtle: '#606060',
                paddingRegular: 10,
                paddingWide: 15,
                sendBoxHeight: 46,
                typingAnimationBackgroundImage: 'url(\'' + M.cfg.wwwroot + '/blocks/tildeva/assets/typing.gif' + '\')',
                typingAnimationWidth: 180,
                bubbleMinHeight: 30,
                suggestedActionBackground: 'transparent',
                suggestedActionBorder: undefined, // split into 3, null
                suggestedActionBorderColor: '#606060', // defaults to accent
                suggestedActionBorderStyle: 'solid',
                suggestedActionBorderWidth: 1,
                suggestedActionBorderRadius: 0,
                suggestedActionImageHeight: 20,
                suggestedActionTextColor: '#606060',
                suggestedActionDisabledBackground: undefined, // defaults to suggestedActionBackground
                suggestedActionHeight: 40,
                bubbleMaxWidth: '80%',
                bubbleBorderRadius: '0px',
                bubbleFromUserBorderRadius: '0px',
            }
        },
        send_callback: function (tid, outcome, args) {
            try {                
                if (!outcome.responseText || outcome.responseText == null ||  outcome.responseText == ""){
                    outcome.responseText = {"response": "no-data"};
                }
               // console.log(outcome.responseText);
                var data = Y.JSON.parse(outcome.responseText);
                var resp = {};
                for (let key in data) {
                    if (data.hasOwnProperty(key)) {
                        resp[key] = data[key];
                    }
                }
                if (Object.keys(resp).length > 0) {
                    this.directLine.postActivity({
                        from: { id: this.cfg.userid }, // required (from.name is optional)
                        type: 'event',
                        name: 'webchat/context', value: JSON.stringify(data)
                    }).subscribe(
                        id => {
                            
                        },
                        error => console.log("Error posting activity", error)
                    );
                    this.directLine.postActivity({
                        type: 'message',
                        from: { id: this.cfg.userid },
                        channelData: { postBack: true },
                        textFormat: 'plain',
                        text: 'command:processed'
                    }).subscribe(
                        id => { /*console.log("Posted activity, assigned ID ", id); */ },
                        error => console.log("Error posting activity", error)
                    );

                }


            } catch (ex) {
                return;
            }

        },


    };

    gui_ajax.init(cfg);
};
