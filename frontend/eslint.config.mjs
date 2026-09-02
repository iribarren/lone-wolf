import { dirname } from "path";
import { fileURLToPath } from "url";
import { FlatCompat } from "@eslint/eslintrc";
import jsxA11y from "eslint-plugin-jsx-a11y";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const compat = new FlatCompat({
  baseDirectory: __dirname,
});

const eslintConfig = [
  ...compat.extends("next/core-web-vitals", "next/typescript"),
  {
    // The app's accessibility is its strongest quality and the E2E suite selects
    // entirely by role and accessible name. `next/core-web-vitals` enables only
    // a subset of jsx-a11y; the recommended set is what stops a restyle quietly
    // costing semantics. The plugin object itself is already registered by
    // next/core-web-vitals -- re-declaring it errors -- so take the rules only.
    rules: {
      ...jsxA11y.flatConfigs.recommended.rules,
      // The system picker's label wraps a radio and its text sits inside a
      // nested <span>, which is correct markup the default depth of 2 cannot
      // see. Raise the depth rather than flatten the markup.
      "jsx-a11y/label-has-associated-control": ["error", { depth: 4 }],
    },
    settings: {
      // Without this the plugin cannot tell that <Input> renders an <input>, and
      // reports every wrapping <label> in AuthGate as unassociated. This is the
      // mechanism the plugin provides for exactly that; it is not a suppression.
      "jsx-a11y": {
        components: {
          Button: "button",
          Input: "input",
          Textarea: "textarea",
        },
      },
    },
  },
  {
    ignores: [
      "node_modules/**",
      ".next/**",
      "out/**",
      "build/**",
      "next-env.d.ts",
    ],
  },
];

export default eslintConfig;
