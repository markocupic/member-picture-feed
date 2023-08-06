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

// Open file explorer when clicking the mouse inside the fineuploader container.
document.addEventListener("DOMContentLoaded", function (event) {
    let boxes = document.querySelectorAll('.mod_memberPictureFeedUpload .fineuploader-container');

    if (boxes) {
        boxes.forEach(function (box, index) {
            box.addEventListener("click", (event) => {
                event.stopPropagation();
                let container = event.target;
                let parent = container.parentNode;
                if (parent) {
                    let btn = parent.querySelector('input[type="file"][name="fileupload_fineuploader"]');
                    if (btn) {
                        btn.click();
                    }
                }
            });
        });
    }
});
