# wp-cli-Lightbox-command-on-off

This php program is for the [WP-CLI](https://wp-cli.org/).

Switch the Lightbox On and Off for all posts and all pages at once.

Since WordPress 6.4, the Lightbox feature has been added.

Change the comment part of the html in the post. Specifically, add "lightbox":{"enabled":true} in wp:image.

If you want to apply this to a previous site, just add this to wp:image in the html.

I made one that changes all posts in a site at once with WP-CLI.

# DEMO


https://github.com/katsushi-kawamori/wp-cli-Lightbox-command-on-off/assets/165099245/f1658016-1765-45cd-b47e-0d3941745092


# Features
```
/* 1st argument: on to turn the lightbox On; off to turn the lightbox Off */
wp box on
wp box off
/* 2nd argument: can be an ID of a post or image */
wp box off 9110 /* only post id 9110 Off */
wp box on 9031 /* media id 9031 only On */
```

# Requirement

* WordPress Version 6.4 or higher
* WP-CLI

# Author

* [Katsushi Kawamori](https://profiles.wordpress.org/katsushi-kawamori/)

# License

"wp-cli-Lightbox-command-on-off" is under [GPLv2 or later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html).

Thank you!
