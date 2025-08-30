export const devLog = (...args: any[]) => {
  if (import.meta.env?.DEV) {
    // eslint-disable-next-line no-console
    console.log(...args);
  }
};
