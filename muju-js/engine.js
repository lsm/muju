;
(function() {
    // regular expression for matching template tags
    var M_ALL = /{[$_\[\]a-zA-Z0-9]+}/g;
    var M_TPL = /^{[.a-z]+}$/;
    var M_VAR = /^{\$([a-zA-Z0-9]+)}$/;
    var M_BLK = /^{\[_[a-zA-Z0-9]+\]}$/;

    // regular expression for getting tag name
    //var R_ALL = /{[$_\[\]a-zA-Z0-9]+}/g;
    var N_TPL = /(^{)|(}$)/g;
    var N_BLK = /(^{\[_)|(\]}$)/g;

    // types of tag
    var T_TPL = 0;
    var T_VAR = 1;
    var T_BLK = 2;


    /**
     *
     * @constructor
     */
    var Engine = function() {
        this._t = {};
        this._varContent = {};
        this._tags = [];
    };

    Engine.prototype = {

        /**
         * @public
         * @param {String} name of the variable
         * @param {Object} value
         */
        assign: function(name, value) {
            this._t[name] = value;
        },

        /**
         * @public
         * @param {String} content of template
         *
         */
        parse: function(content) {
            var segments = this._preParse(content);
            this._content = content;
            if (segments.length > 0) {
                for (var idx in segments) {
                    var segment = segments[idx];
                    switch (segment.type) {
                        case T_VAR:
                            this._varParse(segment);
                            break;
                        case T_BLK:
                            var blkName = segment.name;
                            var replace = this._t.hasOwnProperty(blkName) ? this._t[blkName] : false;
                            this._content = this._blockParse(segment, replace, this._content);
                            break;
                        case T_TPL:
                            break;
                        default:
                            throw new Error("Unknown template tag type");
                    }
                }

            }
            return this._postParse(this._content);
        },
    
        /**
         * @private
         * @param {String} text of template
         * @returns {Array} array of tags
         */
        _preParse: function(content) {
            this._tags = content.match(M_ALL);
            var tags = this._tags;
            if (tags) {
                var segments = [];
                for (var i = 0 ; i < tags.length ; i++) {
                    if (M_TPL.exec(tags[i])) {
                        segments[i] = {
                            name: tags[i].replace(N_TPL,''),
                            type: T_TPL,
                            tag: tags[i]
                        };
                        continue;
                    }

                    if (M_BLK.exec(tags[i])) {
                        segments[i] = this._preParseBlock(tags[i], i);
                        i = this.offset;
                        continue;
                    }

                    var match;
                    if (match = tags[i].match(M_VAR)) {
                        segments[i] = {
                            name: match[1],
                            type: T_VAR,
                            tag: match[0]
                        };
                    }
                }
                return segments;
            }
            return false;
        },

        /**
         * Pre-parse a block
         *
         * @private
         * @param {String} string of open tag
         * @param {Array} array of tags
         * @param {int} index of segments
         * @returns {Array} block information
         */
        _preParseBlock: function(openTag, offset) {
            var tmp = {};
            tmp['name'] = openTag.replace(N_BLK, "");
            var closeTag = '{[' + tmp['name'] + '_]}';
            tmp['open'] = openTag;
            tmp['close'] = closeTag;
            tmp['type'] = T_BLK;
            tmp['keys'] = [];
            tmp['child'] = [];
            var tags = this._tags;

            for (j = offset+1; j < tags.indexOf(closeTag); j++) {
                if (M_BLK.exec(tags[j])) {
                    tmp['child'].push(this._preParseBlock(tags[j], j));
                    continue;
                }
                var match;
                if (match = tags[j].match(M_VAR)) {
                    tmp['keys'].push(match[1]);
                }
            
            // @todo consider how to get global variables
            /*
            if (preg_match('/^{\_(\<\w+\>)?([a-zA-Z0-9]+)}/', segments[i],match)) {
                var['real_name'] = segments[i];
                var['name'] = match[2];
                var['function'] = isset(match[1]) ? match[1] : NULL;
                this._varParse(var);
            }
        */
            }
            this.offset = j;
            return tmp;
        },



        /**
         * Parse a block
         *
         * @param array pattern
         * @param array replace
         * @param string subject
         * @return string
         */
        _blockParse: function(pattern, replace, subject) {

            var open = subject.indexOf(pattern['open']),
            close = subject.indexOf(pattern['close']),
            tagLen = pattern['open'].length,
            replacement = '',
            tmp,
            blockContent = subject.slice(open+tagLen, close);

            if (replace instanceof Array) {
                for (var idx in replace) {
                    tmp = blockContent;
                    if (pattern['child'].length > 0) {
                        for (var childIdx in pattern['child']) {
                            var childPattern = pattern['child'][childIdx];
                            var childReplace = '';
                            if (childPattern.name in replace[idx]) {
                                childReplace = replace[idx][childPattern.name];
                            }/* else if (childPattern['name'] in this._t) {
                            childReplace = this._t[childPattern['name']];
                        }*/
                            tmp = this._blockParse(childPattern, childReplace, tmp);
                        }
                    }

                    if (pattern['keys'] instanceof Array) {
                        for (var item in pattern['keys']) {
                            var key = pattern['keys'][item];
                            tmp = this._replace('{$' + key + '}', key in replace[idx] ? replace[idx][key] : '', tmp);
                        }
                    }
                    replacement += tmp;
                }
            }
            this._saveTag(pattern['open']);
            this._saveTag(pattern['close']);
            open = subject.indexOf(pattern['open']);
            close = subject.indexOf(pattern['close']);
            return subject.slice(0, open) + replacement + subject.slice(close + tagLen);
        },

    /**
     * Post parse prepared segments from one file, remove unassigned.
     *
     * @param string $content content need to post parse
     * @return string
     */
    _postParse: function(content)
    {
        /*
        foreach (this._varContent as $tag => $val) {//parsing var
            $content = $this._replace($tag, $val, $content);
        }*/
        for (var key in this._varContent) {
            var reg = new RegExp('{\\$' + key + '}', 'g');
            content = content.replace(reg, this._varContent[key]);
        }

        for(var idx in this._tags) {//replace not assigned
            content = content.replace(this._tags[idx], '');
        }
        return content;
    },

        _varParse: function(segment) {
            if (segment['name'] in this._t) {
                if (!this._varContent.hasOwnProperty(segment['name'])) {
                    this._varContent[segment['name']] = this._t[segment['name']];
                }
            }
            this._saveTag(segment['name']);
        },

        _saveTag: function(tag) {
            if (this._tags.indexOf(tag) === -1) {
                this._tags.push(tag);
            }
        },

        /**
         * Replace placeholder/tags with actual value
         *
         * @param string search
         * @param string replace
         * @param string subject
         * @return string
         */
        _replace: function(search, replace, subject) {
            return subject.replace(search, replace);
        }

    }

    if (exports) exports.Engine = Engine;
})();