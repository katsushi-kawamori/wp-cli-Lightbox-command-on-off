# wp-cli-Lightbox-command-on-off

This php program is for the [WP-CLI](https://wp-cli.org/).

Switch the Lightbox On and Off for all posts and all pages at once.

Since WordPress 6.4, the Lightbox feature has been added.

Change the comment part of the html in the post. Specifically, add "lightbox":{"enabled":true} in wp:image.

Conversion from the Classic Editor is also supported.

I made one that changes all posts in a site at once with WP-CLI.

# Features
```
/* 1st argument: on to turn the lightbox On; off to turn the lightbox Off */
wp box on
wp box off

1st argument(string) : on -> Lightbox On, off : Lightbox Off
optional argument(int or string) : --exclude=1 or --exclude=1,2,3 : Post ID -> Exclude and process the specified IDs.
optional argument(int or string) : --include=1 or --include=1,2,3 : Post ID -> Process only specified IDs.
optional argument(string) : --size=large : Media size -> Convert to specified image size.

/* sample optional argument: can be an ID of a post */
wp box off --include=9110 /* only post id 9110 Off */
wp box on --include=9031 /* post id 9031 only On */
```

# Requirement

* WordPress Version 6.4 or higher
* WP-CLI

# Author

* [Katsushi Kawamori](https://profiles.wordpress.org/katsushi-kawamori/)

# License

"wp-cli-Lightbox-command-on-off" is under [GPLv2 or later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html).

Thank you!
