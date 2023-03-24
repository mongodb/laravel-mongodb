import { defineConfig } from "vitepress";

// https://vitepress.dev/reference/site-config
export default defineConfig({
    title: "Laravel MongoDB",
    description: "A Laravel Package that will easily setup your project in connecting to mongodb",
    head: [["link", { rel: "icon", type: "image/x-icon", href: "/fav.png" }]],
    themeConfig: {
        editLink: {
            pattern: "https://github.com/BroJenuel/laravel-mongodb/tree/master/docs/:path",
            text: "Edit this page on Github",
        },
        socialLinks: [
            {
                icon: "github",
                link: "https://github.com/BroJenuel/laravel-mongodb",
            },
        ],
        nav: [
            { text: "Home", link: "/" },
            { text: "Guide", link: "/v3/guide/getting-started/installation", activeMatch: "/v3/guide/" },
            {
                text: "Version",
                items: [
                    {
                        text: "v1.*",
                        activeMatch: "/v1/",
                        link: "/v1/version-one",
                    },
                    {
                        text: "v2.*",
                        activeMatch: "/v2/",
                        link: "/v2/version-two",
                    },
                    {
                        text: "v3.*",
                        activeMatch: "/v3/",
                        link: "/v3/guide/getting-started/installation",
                    },
                ],
            },
        ],

        sidebar: {
            "/v3/": [
                {
                    text: "Getting Started",
                    collapsed: false,
                    items: [
                        { text: "Installation", link: "/v3/guide/getting-started/installation" },
                        { text: "Testing", link: "/v3/guide/getting-started/testing" },
                        { text: "Database Testing", link: "/v3/guide/getting-started/database_testing" },
                        { text: "Configuration", link: "/v3/guide/getting-started/configuration" },
                    ],
                },
                {
                    text: "Eloquent",
                    collapsed: true,
                    items: [
                        { text: "Basic Usage", link: "/v3/guide/eloquent/basic-usage" },
                        { text: "Extending The Base Model", link: "/v3/guide/eloquent/extending-the-base-model.md" },
                        {
                            text: "Extending The Base Model",
                            link: "/v3/guide/eloquent/extending-the-auth-base-model.md",
                        },
                        { text: "Soft Deletes", link: "/v3/guide/eloquent/soft-deletes" },
                        { text: "Guarding Attributes", link: "/v3/guide/eloquent/guarding-attributes" },
                        { text: "Dates", link: "/v3/guide/eloquent/dates" },
                        { text: "MongoDB-Specific Operators", link: "/v3/guide/eloquent/mongodb-specific-operators" },
                        {
                            text: "MongoDB-Specific GEO Operations",
                            link: "/v3/guide/eloquent/mongodb-specific-geo-operations",
                        },
                        {
                            text: "Inserts, Updates, and Deletes",
                            link: "/v3/guide/eloquent/insert-updates-and-deletes",
                        },
                        {
                            text: "MongoDB Specific OPERATIONS",
                            link: "/v3/guide/eloquent/mongodb-specific-operations",
                        },
                    ],
                },
                {
                    text: "Relationships",
                    collapsed: true,
                    items: [
                        {
                            text: "Basic Usage",
                            link: "/v3/guide/relationships/basic-usage",
                        },
                        {
                            text: "belongsToMany and pivots",
                            link: "/v3/guide/relationships/belongs-to-many-and-pivots",
                        },
                        {
                            text: "EmbedsMany Relationship",
                            link: "/v3/guide/relationships/embeds-many-relationships",
                        },
                        {
                            text: "EmbedsOne Relationship",
                            link: "/v3/guide/relationships/embeds-one-relationship",
                        },
                    ],
                },
                {
                    text: "Query Builder",
                    collapsed: true,
                    items: [
                        {
                            text: "Basic Usage",
                            link: "/v3/guide/query-builder/basic-usage",
                        },
                        {
                            text: "Available Operators",
                            link: "/v3/guide/query-builder/available-operations",
                        },
                    ],
                },
                {
                    text: "Transactions",
                    collapsed: true,
                    items: [
                        {
                            text: "Basic Usage",
                            link: "/v3/guide/transactions/basic-usage",
                        },
                    ],
                },
                {
                    text: "Schema",
                    collapsed: true,
                    items: [
                        {
                            text: "Basic Usage",
                            link: "/v3/guide/schema/basic-usage",
                        },
                        {
                            text: "Geospatial indexes",
                            link: "/v3/guide/schema/geospatial-indexes",
                        },
                    ],
                },
                {
                    text: "Extending",
                    collapsed: true,
                    items: [
                        {
                            text: "Cross-Database Relationships",
                            link: "/v3/guide/extending/cross-database-relationships",
                        },
                        {
                            text: "Authentication",
                            link: "/v3/guide/extending/authentication",
                        },
                        {
                            text: "Queues",
                            link: "/v3/guide/extending/queues",
                        },
                    ],
                },
                {
                    text: "Upgrading",
                    collapsed: true,
                    items: [
                        {
                            text: "Upgrading from version 2 to 3",
                            link: "/v3/guide/upgrading/upgrading-from-version-2-to-3",
                        },
                    ],
                },
                {
                    text: "Security Contact Information",
                    collapsed: true,
                    items: [
                        {
                            text: "Security Contact Information",
                            link: "/v3/guide/security-contact-information/security-contact-information",
                        },
                    ],
                },
            ],
        },
    },
});
