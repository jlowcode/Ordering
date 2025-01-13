/**
 * Ordering Element
 *
 * @copyright: Copyright (C) 2024 Jlowcode Org - All rights reserved.
 * @license:   GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

define(['jquery', 'fab/element', 'fab/encoder', 'fab/fabrik'], function (jQuery, FbElement, Encoder, Fabrik, AutoComplete) {
    FbOrdering = new Class({
        Extends: FbElement,

        options: {
            tags: []
        },

        initialize: function (element, options) {
            this.setPlugin('ordering');
            this.parent(element, options);
            this.init();
        },

        init: function () {
            var self = this;
            var tree = jQuery(self.element).find('.tree-ordering-single');

            if(self.options.refTree == false) {
                console.warn(Joomla.JText._("PLG_FABRIK_ELEMENT_ORDERING_TYPE_REF_ELEMENT_ERROR"));
                return;
            }

            if (typeOf(this.element) === 'null') {
                return;
            }

            self.configureRefTree(self.options.refTree);
            self.buildTree(self.options.defaultTree ? self.options.defaultTree : '');

            tree.off('tree.click').on('tree.click', function(event) {
                var node = event.node;
                self.addTag(node.name, node.id, true);
            })
        },

        // Build the tree making an AJAX request getting only the root nodes
        buildTree: function (value) {
            const self = this;

            jQuery.ajax({
                url: '',
                method: 'post',
                data: {
                    option: 'com_fabrik',
                    format: 'raw',
                    task: 'plugin.pluginAjax',
                    g: 'element',
                    plugin: 'ordering',
                    method: 'getTree',
                    value: value,
                    listId: self.options.listId,
                    refTreeId: self.options.refTreeId,
                    htmlName: self.options.element
                }
            }).done(function(r) {
                var result = JSON.parse(r);
                var tree = jQuery('#'+result['htmlName']).find('.tree-ordering-single');

                if(!result['success']) {
                    console.warn(result['msg']);
                    return;
                }

                var res2 = result['data'];
                var data = [];

                if(res2.length == 0) {
                    tree.closest('.fabrikSubElementContainer').find('.tag-container').html('');
                    tree.closest('.fabrikSubElementContainer').find('.tag-container').css('display', 'none');
                    tree.html(Joomla.JText._("PLG_FABRIK_ELEMENT_ORDERING_NO_CHILDREN"));
                    tree.css('color', '#666');
                    return;
                }

                res2.forEach(node => {
                    var child = [];
                    child.id = node[0]
                    child.name = node[1];
                    data.push(child);
                });

                tree.tree({
                    data: data,
                    selectable: false
                });
                tree.tree('loadData', data);
            })
        },

        // Add a listener in ref tree element to rebuild the order always the parent node change
        configureRefTree: function (refTree) {
            var self = this;
            var tree = jQuery('#'+refTree).find('.jqtree_common');
            var ref = jQuery('#'+refTree);

            ref.on('click', function() {
                value = jQuery(this).find('input[name="' + refTree + '[]"]').val();
                self.buildTree(value);
            });
        },

        // This function render a tag in element
        addTag: function (text, id) {
            var self = this;

            var tag = {
                id: id,
                text: text,
                container: document.createElement('div'),
                content: document.createElement('span'),
                input: document.createElement('input'),
                closeButton: document.createElement('span')
            };

            tag.container.classList.add('tag-container');
            tag.content.classList.add('tag-content');
            tag.closeButton.classList.add('tag-close-button');

            tag.input.value = id;
            tag.input.setAttribute('type', 'checkbox');
            tag.input.setAttribute('style', 'display: none');
            tag.input.setAttribute('checked', 'checked');
            tag.input.setAttribute('name', self.options.elName);
            tag.input.setAttribute('hidden', true);

            jQuery(self.element).find('.selected-checkbox').append(tag.input);

            tag.content.textContent = tag.text;
            tag.closeButton.textContent = 'x';

            tag.closeButton.addEventListener('click', function () {
                self.removeTag(self.options.tags.indexOf(tag));
            });

            tag.container.appendChild(tag.content);
            tag.container.appendChild(tag.closeButton);

            self.removeTag(0);
            self.options.tags[0] = tag;

            tree = jQuery(self.element).find('.tree-ordering-single');
            jQuery(self.element).find('.tree-ordering').before(tag.container);
        },

        // This function remove a tag in element
        removeTag: function (i) {
            var self = this;
            var tag = self.options.tags[i];

            if (tag) {
                self.options.tags.splice(i, 1);
                jQuery(tag.container).remove();
                jQuery(tag.input).remove();
            }
        }
    });

    return FbOrdering;
});