import type { Config } from "tailwindcss";

const config: Config = {
  content: ["./src/**/*.{js,ts,jsx,tsx,mdx}"],
  theme: {
    extend: {
      colors: {
        brand: {
          50: "#eef7f1",
          100: "#d8ecdf",
          500: "#2f855a",
          600: "#26734c",
          700: "#1f6b45",
          800: "#185338"
        },
        ink: "#202923",
        muted: "#6b756e",
        canvas: "#f6f8f6"
      },
      boxShadow: {
        soft: "0 8px 28px rgba(25, 54, 36, 0.06)"
      }
    }
  },
  plugins: []
};

export default config;
