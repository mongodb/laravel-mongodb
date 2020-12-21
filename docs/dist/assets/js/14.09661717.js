(window.webpackJsonp=window.webpackJsonp||[]).push([[14],{364:function(t,a,e){"use strict";e.r(a);var s=e(42),n=Object(s.a)({},(function(){var t=this,a=t.$createElement,e=t._self._c||a;return e("ContentSlotsDistributor",{attrs:{"slot-key":t.$parent.slotKey}},[e("h1",{attrs:{id:"installation"}},[e("a",{staticClass:"header-anchor",attrs:{href:"#installation"}},[t._v("#")]),t._v(" Installation")]),t._v(" "),e("p",[t._v("Make sure you have the MongoDB PHP driver installed. You can find installation instructions at http://php.net/manual/en/mongodb.installation.php")]),t._v(" "),e("h2",{attrs:{id:"installing"}},[e("a",{staticClass:"header-anchor",attrs:{href:"#installing"}},[t._v("#")]),t._v(" Installing")]),t._v(" "),e("p",[t._v("Install the package via Composer:")]),t._v(" "),e("div",{staticClass:"language-bash extra-class"},[e("pre",{pre:!0,attrs:{class:"language-bash"}},[e("code",[t._v("$ "),e("span",{pre:!0,attrs:{class:"token function"}},[t._v("composer")]),t._v(" require jenssegers/mongodb\n")])])]),e("h3",{attrs:{id:"laravel"}},[e("a",{staticClass:"header-anchor",attrs:{href:"#laravel"}},[t._v("#")]),t._v(" Laravel")]),t._v(" "),e("p",[t._v("In case your Laravel version does NOT autoload the packages, add the service provider to "),e("code",[t._v("config/app.php")]),t._v(":")]),t._v(" "),e("div",{staticClass:"language-php extra-class"},[e("pre",{pre:!0,attrs:{class:"language-php"}},[e("code",[t._v("Jenssegers\\"),e("span",{pre:!0,attrs:{class:"token package"}},[t._v("Mongodb"),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("\\")]),t._v("MongodbServiceProvider")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(":")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(":")]),e("span",{pre:!0,attrs:{class:"token keyword"}},[t._v("class")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(",")]),t._v("\n")])])]),e("h3",{attrs:{id:"lumen"}},[e("a",{staticClass:"header-anchor",attrs:{href:"#lumen"}},[t._v("#")]),t._v(" Lumen")]),t._v(" "),e("p",[t._v("For usage with "),e("a",{attrs:{href:"http://lumen.laravel.com",target:"_blank",rel:"noopener noreferrer"}},[t._v("Lumen"),e("OutboundLink")],1),t._v(", add the service provider in "),e("code",[t._v("bootstrap/app.php")]),t._v(". In this file, you will also need to enable Eloquent. You must however ensure that your call to "),e("code",[t._v("$app->withEloquent();")]),t._v(" is "),e("strong",[t._v("below")]),t._v(" where you have registered the "),e("code",[t._v("MongodbServiceProvider")]),t._v(":")]),t._v(" "),e("div",{staticClass:"language-php extra-class"},[e("pre",{pre:!0,attrs:{class:"language-php"}},[e("code",[e("span",{pre:!0,attrs:{class:"token variable"}},[t._v("$app")]),e("span",{pre:!0,attrs:{class:"token operator"}},[t._v("-")]),e("span",{pre:!0,attrs:{class:"token operator"}},[t._v(">")]),e("span",{pre:!0,attrs:{class:"token function"}},[t._v("register")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("(")]),t._v("Jenssegers\\"),e("span",{pre:!0,attrs:{class:"token package"}},[t._v("Mongodb"),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("\\")]),t._v("MongodbServiceProvider")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(":")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(":")]),e("span",{pre:!0,attrs:{class:"token keyword"}},[t._v("class")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(")")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(";")]),t._v("\n\n"),e("span",{pre:!0,attrs:{class:"token variable"}},[t._v("$app")]),e("span",{pre:!0,attrs:{class:"token operator"}},[t._v("-")]),e("span",{pre:!0,attrs:{class:"token operator"}},[t._v(">")]),e("span",{pre:!0,attrs:{class:"token function"}},[t._v("withEloquent")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("(")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(")")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(";")]),t._v("\n")])])]),e("p",[t._v("The service provider will register a MongoDB database extension with the original database manager. There is no need to register additional facades or objects.")]),t._v(" "),e("p",[t._v("When using MongoDB connections, Laravel will automatically provide you with the corresponding MongoDB objects.")]),t._v(" "),e("h3",{attrs:{id:"non-laravel-projects"}},[e("a",{staticClass:"header-anchor",attrs:{href:"#non-laravel-projects"}},[t._v("#")]),t._v(" Non-Laravel projects")]),t._v(" "),e("p",[t._v("For usage outside Laravel, check out the "),e("a",{attrs:{href:"https://github.com/illuminate/database/blob/master/README.md",target:"_blank",rel:"noopener noreferrer"}},[t._v("Capsule manager"),e("OutboundLink")],1),t._v(" and add:")]),t._v(" "),e("div",{staticClass:"language-php extra-class"},[e("pre",{pre:!0,attrs:{class:"language-php"}},[e("code",[e("span",{pre:!0,attrs:{class:"token variable"}},[t._v("$capsule")]),e("span",{pre:!0,attrs:{class:"token operator"}},[t._v("-")]),e("span",{pre:!0,attrs:{class:"token operator"}},[t._v(">")]),e("span",{pre:!0,attrs:{class:"token function"}},[t._v("getDatabaseManager")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("(")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(")")]),e("span",{pre:!0,attrs:{class:"token operator"}},[t._v("-")]),e("span",{pre:!0,attrs:{class:"token operator"}},[t._v(">")]),e("span",{pre:!0,attrs:{class:"token function"}},[t._v("extend")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("(")]),e("span",{pre:!0,attrs:{class:"token single-quoted-string string"}},[t._v("'mongodb'")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(",")]),t._v(" "),e("span",{pre:!0,attrs:{class:"token keyword"}},[t._v("function")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("(")]),e("span",{pre:!0,attrs:{class:"token variable"}},[t._v("$config")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(",")]),t._v(" "),e("span",{pre:!0,attrs:{class:"token variable"}},[t._v("$name")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(")")]),t._v(" "),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("{")]),t._v("\n    "),e("span",{pre:!0,attrs:{class:"token variable"}},[t._v("$config")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("[")]),e("span",{pre:!0,attrs:{class:"token single-quoted-string string"}},[t._v("'name'")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("]")]),t._v(" "),e("span",{pre:!0,attrs:{class:"token operator"}},[t._v("=")]),t._v(" "),e("span",{pre:!0,attrs:{class:"token variable"}},[t._v("$name")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(";")]),t._v("\n\n    "),e("span",{pre:!0,attrs:{class:"token keyword"}},[t._v("return")]),t._v(" "),e("span",{pre:!0,attrs:{class:"token keyword"}},[t._v("new")]),t._v(" "),e("span",{pre:!0,attrs:{class:"token class-name"}},[t._v("Jenssegers"),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("\\")]),t._v("Mongodb"),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("\\")]),t._v("Connection")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("(")]),e("span",{pre:!0,attrs:{class:"token variable"}},[t._v("$config")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(")")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(";")]),t._v("\n"),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v("}")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(")")]),e("span",{pre:!0,attrs:{class:"token punctuation"}},[t._v(";")]),t._v("\n")])])]),e("h2",{attrs:{id:"laravel-version-compatibility"}},[e("a",{staticClass:"header-anchor",attrs:{href:"#laravel-version-compatibility"}},[t._v("#")]),t._v(" Laravel version Compatibility")]),t._v(" "),e("table",[e("thead",[e("tr",[e("th",[t._v("Laravel")]),t._v(" "),e("th",{staticStyle:{"text-align":"center"}},[t._v("Package")])])]),t._v(" "),e("tbody",[e("tr",[e("td",[t._v("4.2.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("2.0.x")])]),t._v(" "),e("tr",[e("td",[t._v("5.0.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("2.1.x")])]),t._v(" "),e("tr",[e("td",[t._v("5.1.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("2.2.x or 3.0.x")])]),t._v(" "),e("tr",[e("td",[t._v("5.2.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("2.3.x or 3.0.x")])]),t._v(" "),e("tr",[e("td",[t._v("5.3.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("3.1.x or 3.2.x")])]),t._v(" "),e("tr",[e("td",[t._v("5.4.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("3.2.x")])]),t._v(" "),e("tr",[e("td",[t._v("5.5.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("3.3.x")])]),t._v(" "),e("tr",[e("td",[t._v("5.6.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("3.4.x")])]),t._v(" "),e("tr",[e("td",[t._v("5.7.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("3.4.x")])]),t._v(" "),e("tr",[e("td",[t._v("5.8.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("3.5.x")])]),t._v(" "),e("tr",[e("td",[t._v("6.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("3.6.x")])]),t._v(" "),e("tr",[e("td",[t._v("7.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("3.7.x")])]),t._v(" "),e("tr",[e("td",[t._v("8.x")]),t._v(" "),e("td",{staticStyle:{"text-align":"center"}},[t._v("3.8.x")])])])])])}),[],!1,null,null,null);a.default=n.exports}}]);