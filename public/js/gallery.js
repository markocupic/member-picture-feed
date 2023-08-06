/*
 * This file is part of Member Picture Feed.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/member-picture-feed
 */

"use strict";

class MemberPictureFeedUploadApp {
    constructor(requestToken, pageId, pageLanguage) {
        this.requestToken = requestToken;
        this.pageId = pageId;
        this.pageLanguage = pageLanguage;
    }


    initialize() {
        const REQUEST_TOKEN = this.requestToken;
        const PAGE_ID = this.pageId;
        const PAGE_LANGUAGE = this.pageLanguage;
        const ELEMENT_CAPTION_MODAL = document.querySelector('.modal.member-picture-feed-caption-modal');
        let CURRENT_FILE_ID;

        // Remove image
        ['click', 'touchmove'].forEach(function (e) {
            const buttons = document.querySelectorAll('.remove-image');
            if (buttons) {
                buttons.forEach((button) => {
                    button.addEventListener(e, function (event) {

                        event.preventDefault();
                        const image = button.closest('[data-file-id]');

                        if (image) {
                            CURRENT_FILE_ID = image.getAttribute('data-file-id');

                            if (CURRENT_FILE_ID !== undefined) {

                                const formData = new FormData();
                                formData.append('REQUEST_TOKEN', REQUEST_TOKEN);
                                formData.append('fileId', CURRENT_FILE_ID);

                                fetch('_member_picture_feed_xhr/remove_image', {
                                    method: 'POST',
                                    headers: {
                                        'x-requested-with': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                    },
                                    body: formData,
                                })
                                .then((response) => response.json())
                                .then((data) => {
                                    if (data.message) {
                                        if (data.status === 'success') {
                                            console.log(data.message);
                                        } else {
                                            console.error(data.message);
                                        }
                                    }

                                    if (data.status === 'success') {
                                        image.remove();
                                        location.reload();
                                    } else {
                                        console.error('Server error!!!')
                                    }
                                })
                                .catch((error) => {
                                    console.error('Error:', error);
                                });
                            }
                        }
                    });
                });
            }
        });


        // Rotate image
        ['click', 'touchmove'].forEach(function (e) {
            const buttons = document.querySelectorAll('.rotate-image');
            if (buttons) {
                buttons.forEach((button) => {
                    button.addEventListener(e, function (event) {

                        event.preventDefault();
                        const image = button.closest('[data-file-id]');

                        if (image) {
                            CURRENT_FILE_ID = image.getAttribute('data-file-id');

                            if (CURRENT_FILE_ID !== undefined) {

                                const formData = new FormData();
                                formData.append('REQUEST_TOKEN', REQUEST_TOKEN);
                                formData.append('fileId', CURRENT_FILE_ID);

                                fetch('_member_picture_feed_xhr/rotate_image', {
                                    method: 'POST',
                                    headers: {
                                        'x-requested-with': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                    },
                                    body: formData,
                                })
                                .then((response) => response.json())
                                .then((data) => {

                                    if (data.message) {
                                        if (data.status === 'success') {
                                            console.log(data.message);
                                        } else {
                                            console.error(data.message);
                                        }
                                    }

                                    if (data.status === 'success') {
                                        location.reload();
                                    } else {
                                        console.error('Server error!!!')

                                    }
                                })
                                .catch((error) => {
                                    console.error('Error:', error);
                                });
                            }
                        }
                    });
                });
            }

        });


        // Open modal to edit caption and photographer name
        const editCaptionButtons = document.querySelectorAll('button.add-caption');

        if (editCaptionButtons) {
            editCaptionButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    const image = button.closest('[data-file-id]');
                    if (image) {
                        CURRENT_FILE_ID = image.getAttribute('data-file-id');
                        const filePath = image.getAttribute('data-src');
                        if (CURRENT_FILE_ID) {
                            const elModal = ELEMENT_CAPTION_MODAL;
                            const bsModal = bootstrap.Modal.getOrCreateInstance(ELEMENT_CAPTION_MODAL);


                            elModal.querySelector('.image-full-res').setAttribute('src', filePath);

                            const formData = new FormData();
                            formData.append('REQUEST_TOKEN', REQUEST_TOKEN);
                            formData.append('pageLanguage', PAGE_LANGUAGE);
                            formData.append('pageId', PAGE_ID);

                            formData.append('fileId', CURRENT_FILE_ID);

                            fetch('_member_picture_feed_xhr/get_image_data', {
                                method: 'POST',
                                headers: {
                                    'x-requested-with': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                                body: formData,
                            })
                            .then((response) => response.json())
                            .then((data) => {
                                if (data.message) {
                                    if (data.status === 'success') {
                                        console.log(data.message);
                                    } else {
                                        console.error(data.message);
                                    }
                                }

                                if (data.status === 'success') {
                                    bsModal.show();
                                    document.getElementById('imageCaptionInput').setAttribute('value', data.caption);
                                    document.getElementById('imagePhotographerInput').setAttribute('value', data.photographer);
                                } else {
                                    console.error('Server error!!!')
                                }
                            })
                            .catch((error) => {
                                console.error('Error:', error);
                            });
                        }
                    }
                });
            });

        }

        // Save caption to the server
        document.getElementById('saveCaptionButton').addEventListener('click', () => {
            const elModal = ELEMENT_CAPTION_MODAL;
            const bsModal = bootstrap.Modal.getOrCreateInstance(ELEMENT_CAPTION_MODAL)
            const caption = elModal.querySelector('[name="image-caption"]').value;
            const photographer = elModal.querySelector('[name="image-photographer"]').value;

            bsModal.hide();

            const formData = new FormData();
            formData.append('REQUEST_TOKEN', REQUEST_TOKEN);
            formData.append('pageLanguage', PAGE_LANGUAGE);
            formData.append('caption', caption);
            formData.append('photographer', photographer);
            formData.append('fileId', CURRENT_FILE_ID);

            fetch('_member_picture_feed_xhr/set_caption', {
                method: 'POST',
                headers: {
                    'x-requested-with': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
            })
            .then((response) => response.json())
            //Then with the data from the response in JSON...
            .then((data) => {
                if (data.message) {
                    if (data.status === 'success') {
                        console.log(data.message);
                    } else {
                        console.error(data.message);
                    }
                }
            })
            //Then with the error genereted...
            .catch((error) => {
                console.error('Error:', error);
            });
        });

    }
}

