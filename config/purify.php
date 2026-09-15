<?php

return [

    // Overrides only the config set; every other key falls back to the package
    // default (vendor/stevebauman/purify/config/purify.php) via the config merge.
    'configs' => [

        'default' => [
            'Core.Encoding' => 'utf-8',
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            // li/p gain `class` so Quill's indent levels survive; the allow-list
            // below keeps that to the ql-indent-N classes and nothing else.
            'HTML.Allowed' => 'h1,h2,h3,h4,h5,h6,b,u,strong,i,em,s,del,a[href|title],ul,ol,li[class],p[style|class],br,span,img[width|height|alt|src],blockquote',
            'HTML.ForbiddenElements' => '',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
            'Attr.AllowedClasses' => 'ql-indent-1,ql-indent-2,ql-indent-3,ql-indent-4,ql-indent-5,ql-indent-6,ql-indent-7,ql-indent-8',
        ],

    ],

];
