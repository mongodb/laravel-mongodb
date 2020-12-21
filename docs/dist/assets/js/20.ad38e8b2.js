(window.webpackJsonp=window.webpackJsonp||[]).push([[20],{371:function(s,t,a){"use strict";a.r(t);var e=a(42),n=Object(e.a)({},(function(){var s=this,t=s.$createElement,a=s._self._c||t;return a("ContentSlotsDistributor",{attrs:{"slot-key":s.$parent.slotKey}},[a("h1",{attrs:{id:"upgrading"}},[a("a",{staticClass:"header-anchor",attrs:{href:"#upgrading"}},[s._v("#")]),s._v(" Upgrading")]),s._v(" "),a("h2",{attrs:{id:"upgrading-from-version-2-to-3"}},[a("a",{staticClass:"header-anchor",attrs:{href:"#upgrading-from-version-2-to-3"}},[s._v("#")]),s._v(" Upgrading from version 2 to 3")]),s._v(" "),a("p",[s._v("In this new major release which supports the new MongoDB PHP extension, we also moved the location of the Model class and replaced the MySQL model class with a trait.")]),s._v(" "),a("p",[s._v("Please change all "),a("code",[s._v("Jenssegers\\Mongodb\\Model")]),s._v(" references to "),a("code",[s._v("Jenssegers\\Mongodb\\Eloquent\\Model")]),s._v(" either at the top of your model files or your registered alias.")]),s._v(" "),a("div",{staticClass:"language-php extra-class"},[a("pre",{pre:!0,attrs:{class:"language-php"}},[a("code",[a("span",{pre:!0,attrs:{class:"token keyword"}},[s._v("use")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token package"}},[s._v("Jenssegers"),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("\\")]),s._v("Mongodb"),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("\\")]),s._v("Eloquent"),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("\\")]),s._v("Model")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v(";")]),s._v("\n\n"),a("span",{pre:!0,attrs:{class:"token keyword"}},[s._v("class")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token class-name"}},[s._v("User")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token keyword"}},[s._v("extends")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token class-name"}},[s._v("Model")]),s._v("\n"),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("{")]),s._v("\n    "),a("span",{pre:!0,attrs:{class:"token comment"}},[s._v("//")]),s._v("\n"),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("}")]),s._v("\n")])])]),a("p",[s._v("If you are using hybrid relations, your MySQL classes should now extend the original Eloquent model class "),a("code",[s._v("Illuminate\\Database\\Eloquent\\Model")]),s._v(" instead of the removed "),a("code",[s._v("Jenssegers\\Eloquent\\Model")]),s._v(".")]),s._v(" "),a("p",[s._v("Instead use the new "),a("code",[s._v("Jenssegers\\Mongodb\\Eloquent\\HybridRelations")]),s._v(" trait. This should make things more clear as there is only one single model class in this package.")]),s._v(" "),a("div",{staticClass:"language-php extra-class"},[a("pre",{pre:!0,attrs:{class:"language-php"}},[a("code",[a("span",{pre:!0,attrs:{class:"token keyword"}},[s._v("use")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token package"}},[s._v("Jenssegers"),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("\\")]),s._v("Mongodb"),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("\\")]),s._v("Eloquent"),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("\\")]),s._v("HybridRelations")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v(";")]),s._v("\n\n"),a("span",{pre:!0,attrs:{class:"token keyword"}},[s._v("class")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token class-name"}},[s._v("User")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token keyword"}},[s._v("extends")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token class-name"}},[s._v("Model")]),s._v("\n"),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("{")]),s._v("\n\n    "),a("span",{pre:!0,attrs:{class:"token keyword"}},[s._v("use")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token package"}},[s._v("HybridRelations")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v(";")]),s._v("\n\n    "),a("span",{pre:!0,attrs:{class:"token keyword"}},[s._v("protected")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token variable"}},[s._v("$connection")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token operator"}},[s._v("=")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token single-quoted-string string"}},[s._v("'mysql'")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v(";")]),s._v("\n"),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("}")]),s._v("\n")])])]),a("p",[s._v("Embedded relations now return an "),a("code",[s._v("Illuminate\\Database\\Eloquent\\Collection")]),s._v(" rather than a custom Collection class. If you were using one of the special methods that were available, convert them to Collection operations.")]),s._v(" "),a("div",{staticClass:"language-php extra-class"},[a("pre",{pre:!0,attrs:{class:"language-php"}},[a("code",[a("span",{pre:!0,attrs:{class:"token variable"}},[s._v("$books")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token operator"}},[s._v("=")]),s._v(" "),a("span",{pre:!0,attrs:{class:"token variable"}},[s._v("$user")]),a("span",{pre:!0,attrs:{class:"token operator"}},[s._v("-")]),a("span",{pre:!0,attrs:{class:"token operator"}},[s._v(">")]),a("span",{pre:!0,attrs:{class:"token function"}},[s._v("books")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("(")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v(")")]),a("span",{pre:!0,attrs:{class:"token operator"}},[s._v("-")]),a("span",{pre:!0,attrs:{class:"token operator"}},[s._v(">")]),a("span",{pre:!0,attrs:{class:"token function"}},[s._v("sortBy")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("(")]),a("span",{pre:!0,attrs:{class:"token single-quoted-string string"}},[s._v("'title'")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v(")")]),a("span",{pre:!0,attrs:{class:"token operator"}},[s._v("-")]),a("span",{pre:!0,attrs:{class:"token operator"}},[s._v(">")]),a("span",{pre:!0,attrs:{class:"token function"}},[s._v("get")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v("(")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v(")")]),a("span",{pre:!0,attrs:{class:"token punctuation"}},[s._v(";")]),s._v("\n")])])]),a("h2",{attrs:{id:"security-contact-information"}},[a("a",{staticClass:"header-anchor",attrs:{href:"#security-contact-information"}},[s._v("#")]),s._v(" Security contact information")]),s._v(" "),a("p",[s._v("To report a security vulnerability, follow "),a("a",{attrs:{href:"https://tidelift.com/security",target:"_blank",rel:"noopener noreferrer"}},[s._v("these steps"),a("OutboundLink")],1),s._v(".")])])}),[],!1,null,null,null);t.default=n.exports}}]);