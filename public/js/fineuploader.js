/*
 * This file is part of Member Picture Feed.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/member-picture-feed
 */

"use strict";

// Open file explorer when clicking the mouse inside the fineuploader container.
document.addEventListener("DOMContentLoaded", () => {
    const boxes = document.querySelectorAll('.mod_memberPictureFeedUpload .fineuploader-container');

    if (boxes) {
        for (const box of boxes) {
            box.addEventListener("click", (event) => {
                event.stopPropagation();
                const container = event.target;
                const parent = container.parentNode;
                if (parent) {
                    const btn = parent.querySelector('input[type="file"][name="fileupload_fineuploader"]');
                    if (btn) {
                        btn.click();
                    }
                }
            });
        }
    }
});
